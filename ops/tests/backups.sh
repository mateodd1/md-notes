#!/usr/bin/env bash
set -euo pipefail

# Integration test with temporary data and fake Docker/rclone commands. No network calls.
test_root="$(mktemp -d /tmp/md-notes-backup-test.XXXXXX)"
test_ops="$(cd "$(dirname "$0")/.." && pwd)"
export test_root
cleanup() {
  [[ "${test_root}" == /tmp/md-notes-backup-test.* ]] && rm -rf -- "${test_root}"
}
trap cleanup EXIT
mkdir -p "${test_root}/project/app" "${test_root}/project/data/1" "${test_root}/project/docker" "${test_root}/locks"
for file in db.env app/.env compose.yml Dockerfile; do
  printf 'test configuration\n' > "${test_root}/project/${file}"
done
printf '# Test note\n' > "${test_root}/project/data/1/Note.md"
printf 'test application\n' > "${test_root}/project/app/application.php"

docker() { printf 'CREATE TABLE test (id INT);\n'; }
rclone() {
  [[ "$1" == sync && "$2" == "${test_root}"/backups/snapshots/20* ]]
  [[ -s "$2/md-notes/database.sql" ]]
  # A writer/pruner cannot take the exclusive snapshot lock during upload.
  if flock -n -x "${test_root}/locks/md-notes-snapshots.lock" -c true; then
    echo 'Snapshot was not locked during upload' >&2
    return 1
  fi
  printf '# Mutated current\n' > "${test_root}/backups/current/md-notes/spaces/1/Note.md"
  cmp "$2/md-notes/spaces/1/Note.md" "${test_root}/project/data/1/Note.md"
  printf '%s\n' "$2" >> "${test_root}/uploads"
}
export -f docker rclone

run_local() {
  bash <(sed -e "s|/var/backups/md-notes|${test_root}/backups|g" \
    -e "s|/root/docker/md-notes|${test_root}/project|g" \
    -e "s|/run/lock/|${test_root}/locks/|g" \
    -e 's/^readonly MIN_FREE_KIB=.*/readonly MIN_FREE_KIB=0/' "${test_ops}/backup-md-notes-local")
}
run_remote() {
  bash <(sed -e "s|/var/backups/md-notes|${test_root}/backups|g" \
    -e "s|/run/lock/|${test_root}/locks/|g" \
    -e 's|^/usr/local/sbin/backup-md-notes-local$|true|' "${test_ops}/backup-md-notes-backblaze")
}

run_local
first_snapshot="$(find "${test_root}/backups/snapshots" -mindepth 1 -maxdepth 1 -name '20*' -printf '%f\n')"
[[ -n "${first_snapshot}" ]]
run_local
[[ "$(find "${test_root}/backups/snapshots" -mindepth 1 -maxdepth 1 -name '20*' | wc -l)" == 1 ]]

# Incomplete staged snapshots must be ignored, even with a newer timestamp.
mkdir "${test_root}/backups/snapshots/.pending.2099"
run_remote
[[ "$(<"${test_root}/backups/remote-last-uploaded-snapshot")" == "${first_snapshot}" ]]
run_remote
[[ "$(wc -l < "${test_root}/uploads")" == 1 ]]

# A local pass repairs the mutable working copy without changing the published snapshot.
run_local
cmp "${test_root}/backups/snapshots/${first_snapshot}/md-notes/spaces/1/Note.md" "${test_root}/project/data/1/Note.md"
[[ "$(find "${test_root}/backups/snapshots" -mindepth 1 -maxdepth 1 -name '20*' | wc -l)" == 2 ]]
# Retention removes old snapshots but always retains the newest complete copy.
find "${test_root}/backups/snapshots" -mindepth 1 -maxdepth 1 -name '20*' -exec touch -d '40 days ago' {} +
run_local
[[ "$(find "${test_root}/backups/snapshots" -mindepth 1 -maxdepth 1 -name '20*' | wc -l)" == 1 ]]
echo 'Backup integration checks passed (temporary files only, no remote upload).'
