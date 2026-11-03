{{-- Email textarea + CSV/TSV import, posted as `invite_emails` and `invite_file`. Used on the create form and the Clients tab. --}}
<div>
    <label for="invite_emails" class="label">Email addresses</label>
    <textarea id="invite_emails" name="invite_emails" rows="3" placeholder="diane@example.com, kevin@example.com" class="textarea text-[13px]">{{ old('invite_emails') }}</textarea>
    <p class="help">Separate with commas, semicolons or new lines.</p>
    @error('invite_emails') <p class="error-text">{{ $message }}</p> @enderror
</div>

<div>
    <label for="invite_file" class="label">Or import a CSV or TSV file</label>
    <input id="invite_file" type="file" name="invite_file" accept=".csv,.tsv,.txt"
           class="block w-full cursor-pointer rounded-lg border border-dashed border-border-strong bg-card p-2 text-xs text-muted-foreground transition hover:border-foreground/40 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-accent">
    @error('invite_file') <p class="error-text">{{ $message }}</p> @enderror
</div>
