# md-notes documentation

md-notes is a private space for keeping class notes, ideas, and documentation in Markdown (`.md`) files. It is designed for quick writing, flexible organisation, and keeping content under your own account.

## Getting started

1. Create an account from the home page.
2. In the left panel, create a file or folder with the top buttons, or by right-clicking an empty area.
3. Open a note. It is shown formatted by default; select **Edit** to change its Markdown.
4. Select **Save** when you are finished. The change is saved and creates a history version.

## Organising notes and folders

- Create folders and subfolders from the context menu.
- Drag a note or folder to move it; dropping it on the root area moves it out of all folders.
- Drag notes and folders before or after other items to change their order.
- Right-click a folder to edit its name, colour, and whether it should stay collapsed.
- You can **pin** files and folders. They appear with a pin in the sidebar.
- Right-click a file to rename it, download it, share it, view its history, or open its properties.

## Writing Markdown

Reading mode renders Markdown. The editor has shortcuts for headings, bold, italic, lists, quotes, links, and code.

Examples:

```md
# Main heading

**bold**, *italic*, and `code`.

[A link](https://example.com)
```

Large images are fitted to the available width. Hover an image to reveal its download button.

## Images and file attachments

- Paste an image from the clipboard with `Ctrl` + `V` / `⌘` + `V` while editing a note.
- You can also drag and drop files onto the editor or use the paperclip button.
- Each attachment can be up to 10 MB.
- Attachments referenced by a note count towards your account storage quota.
- **Properties** shows the Markdown size, attachment size, and total size for a note.

## Version history

Open history from the context menu of any `.md` file.

- Up to 50 versions per note are kept for 7 days.
- You can view, download, or restore every available version.
- A version is created when you select **Save**, or when you switch to another note after editing the current one.
- Version storage also counts towards your quota.

## Sharing notes

Select **Share link** from a note’s context menu. You can create links for one hour, 24 hours, 7 days, or with no expiry.

Manage links in **Shared** from the profile menu: you can change their duration or revoke them. A shared note opens without a sign-in and includes the attachments it references.

## Trash and storage

Deleting a file or folder moves it to the trash. You can restore it there or delete it permanently. Trashed items keep their history and continue counting towards your quota.

Every account includes 100 MB for notes, images, attachments, and versions. The indicator at the bottom of the sidebar updates periodically.

## Account and privacy

In **Profile settings**, you can change your name, request a code to change your password, download an export of your data, and delete your account. The export is sent by email through a private link and can be requested once per day.

Each account has its own file space. Notes and attachments in one account cannot be accessed by another. Shared links are the only way to grant public access to a specific note.

## Terminal API

Create a personal token in your profile to upload Markdown notes from the terminal. The token is shown only once, so save it in a password manager.

```bash
curl --fail-with-body -X PUT \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --data-binary @notes.md \
  https://mdnotes.net/api/notes/Class/notes.md
```

The API accepts only `.md` files up to 5 MiB and creates any missing folders. You can send the Markdown file as a raw body, as in the example, or send JSON with a `content` field. Uploaded content is also added to version history.

## Theme and devices

By default, md-notes follows your system’s light or dark theme. You can choose a fixed theme from the profile menu. On mobile, use the **Notes** button to open and close the sidebar.

## Backups

Periodic backups are made to protect the availability of your data.
