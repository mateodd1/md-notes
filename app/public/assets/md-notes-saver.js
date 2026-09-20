/* A single writer per editor: only the exact payload acknowledged by the server is saved. */
class MdNotesSaver {
    constructor(read, send, initial, onState = () => {}) {
        this.read = read;
        this.send = send;
        this.saved = initial;
        this.snapshot = initial;
        this.onState = onState;
        this.pending = null;
    }

    get dirty() { return this.read() !== this.saved; }

    async save(snapshot = false) {
        while (this.pending) {
            const pending = this.pending;
            if (!await pending) return false;
            if (this.pending === pending) this.pending = null;
        }
        if (!this.dirty && (!snapshot || this.read() === this.snapshot)) return true;
        const content = this.read();
        this.pending = (async () => {
            this.onState('saving');
            try {
                await this.send(content, snapshot);
                this.saved = content;
                if (snapshot) this.snapshot = content;
                this.onState(this.dirty ? 'unsaved' : 'saved');
                return true;
            } catch (error) {
                this.onState('error', error);
                return false;
            }
        })();
        const pending = this.pending;
        const succeeded = await pending;
        if (this.pending === pending) this.pending = null;
        // Explicit saves/navigation flush any edits made while the request was running.
        return succeeded && snapshot && this.dirty ? this.save(true) : succeeded;
    }
}
if (typeof module !== 'undefined') module.exports = MdNotesSaver;
else window.MdNotesSaver = MdNotesSaver;
