<!-- User Detail Drawer Component -->
<div x-data="userDetailDrawer()"
     @open-user-drawer.window="openUser($event.detail.id)"
     class="relative z-50">

    <!-- Backdrop -->
    <div x-show="isOpen"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()"
         class="fixed inset-0 bg-foreground/40 backdrop-blur-xs"
         style="display: none;"></div>

    <!-- Slide-over Drawer Panel -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 max-w-2xl w-full bg-card border-l border-border shadow-2xl flex flex-col z-50 overflow-hidden font-sans"
         style="display: none;">

        <!-- Header -->
        <div class="px-6 py-5 border-b border-border bg-secondary/30 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-sm shrink-0 uppercase"
                     x-text="user?.name ? user.name.substring(0, 2) : 'US'">
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-foreground truncate" x-text="user?.name || 'Loading user...'"></h2>
                        <span x-show="user?.role === 'admin'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-destructive/10 text-destructive uppercase">Admin</span>
                    </div>
                    <div class="text-xs text-muted-foreground truncate" x-text="user?.email || ''"></div>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <!-- Impersonate Quick Action -->
                <template x-if="user && user.role !== 'admin' && user.is_active">
                    <form method="POST" :action="`/admin/users/${user.id}/impersonate`">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-xs shadow-sm transition-all cursor-pointer flex items-center gap-1.5">
                            <span>Impersonate</span>
                            <span>&rarr;</span>
                        </button>
                    </form>
                </template>

                <button @click="close()" class="p-2 text-muted-foreground hover:text-foreground rounded-lg hover:bg-secondary transition-colors cursor-pointer text-lg leading-none">
                    &times;
                </button>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex border-b border-border px-6 bg-card shrink-0 text-xs font-semibold text-muted-foreground gap-6">
            <button @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'border-primary text-foreground' : 'border-transparent hover:text-foreground'"
                    class="py-3 border-b-2 transition-colors cursor-pointer flex items-center gap-1.5">
                Overview &amp; Profile
            </button>
            <button @click="activeTab = 'galleries'"
                    :class="activeTab === 'galleries' ? 'border-primary text-foreground' : 'border-transparent hover:text-foreground'"
                    class="py-3 border-b-2 transition-colors cursor-pointer flex items-center gap-1.5">
                Galleries (<span x-text="galleries.length">0</span>)
            </button>
            <button @click="activeTab = 'activities'"
                    :class="activeTab === 'activities' ? 'border-primary text-foreground' : 'border-transparent hover:text-foreground'"
                    class="py-3 border-b-2 transition-colors cursor-pointer flex items-center gap-1.5">
                Activity Timeline (<span x-text="activities.length">0</span>)
            </button>
            <button @click="activeTab = 'notes'"
                    :class="activeTab === 'notes' ? 'border-primary text-foreground' : 'border-transparent hover:text-foreground'"
                    class="py-3 border-b-2 transition-colors cursor-pointer flex items-center gap-1.5">
                Admin Notes (<span x-text="notes.length">0</span>)
            </button>
        </div>

        <!-- Drawer Content Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Loading Spinner -->
            <div x-show="isLoading" class="py-20 text-center text-muted-foreground space-y-2">
                <div class="inline-block w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                <div class="text-xs">Loading user record &amp; activities...</div>
            </div>

            <!-- Error Banner -->
            <div x-show="errorMessage" class="p-4 rounded-xl bg-destructive/10 text-destructive text-xs" x-text="errorMessage" style="display: none;"></div>

            <!-- TAB 1: Overview & Profile -->
            <div x-show="!isLoading && activeTab === 'overview'" class="space-y-6">

                <!-- Email Verification Card -->
                <div class="rounded-xl border border-border bg-secondary/20 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-foreground">Email Verification Status</span>
                            <template x-if="user?.is_email_verified">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-500/10 text-green-700 dark:text-green-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Verified (<span x-text="user?.email_verified_at"></span>)
                                </span>
                            </template>
                            <template x-if="user && !user.is_email_verified">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    Unverified
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Email Verification Actions -->
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <template x-if="user && !user.is_email_verified">
                            <button type="button"
                                    @click="verifyEmail()"
                                    class="px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer">
                                Mark as Verified
                            </button>
                        </template>

                        <template x-if="user?.is_email_verified">
                            <button type="button"
                                    @click="confirmUnverifyModal = true"
                                    class="px-3 py-1.5 rounded-lg border border-border bg-card hover:bg-secondary text-foreground font-semibold text-xs transition-colors cursor-pointer">
                                Mark as Unverified
                            </button>
                        </template>

                        <template x-if="user && !user.is_email_verified">
                            <button type="button"
                                    @click="resendVerificationEmail()"
                                    class="px-3 py-1.5 rounded-lg border border-border bg-card hover:bg-secondary text-foreground font-semibold text-xs transition-colors cursor-pointer">
                                Resend Verification Link
                            </button>
                        </template>

                        <button type="button"
                                @click="triggerPasswordReset()"
                                class="px-3 py-1.5 rounded-lg border border-border bg-card hover:bg-secondary text-foreground font-semibold text-xs transition-colors cursor-pointer">
                            Send Password Reset
                        </button>
                    </div>
                </div>

                <!-- Account Meta Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3.5 rounded-xl border border-border bg-card space-y-1">
                        <div class="text-muted-foreground font-medium">Username / Subdomain</div>
                        <div class="font-semibold text-foreground font-mono">
                            <span x-text="user?.username ? `@${user.username}` : 'None assigned'"></span>
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-border bg-card space-y-1">
                        <div class="text-muted-foreground font-medium">Subscription Tier</div>
                        <div class="font-semibold text-foreground" x-text="user?.plan_name || 'Free'"></div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-border bg-card space-y-1">
                        <div class="text-muted-foreground font-medium">Account Status</div>
                        <div class="font-semibold" :class="user?.is_active ? 'text-green-600' : 'text-destructive'" x-text="user?.is_active ? 'Active' : 'Suspended'"></div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-border bg-card space-y-1">
                        <div class="text-muted-foreground font-medium">Registered Date</div>
                        <div class="font-semibold text-foreground" x-text="user?.joined_at || '-'"></div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-border bg-card space-y-1">
                        <div class="text-muted-foreground font-medium">Phone</div>
                        <div class="font-semibold text-foreground" x-text="user?.phone || 'Not provided'"></div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-border bg-card space-y-1">
                        <div class="text-muted-foreground font-medium">Location</div>
                        <div class="font-semibold text-foreground" x-text="user?.location || 'Not provided'"></div>
                    </div>
                </div>

                <!-- Storage Quota Meter -->
                <div class="p-4 rounded-xl border border-border bg-card space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-foreground">Storage Allocation</span>
                        <span class="text-muted-foreground font-mono" x-text="`${Math.round((user?.storage?.used_bytes || 0) / (1024*1024))} MB / ${user?.storage?.limit_bytes ? Math.round(user.storage.limit_bytes / (1024*1024*1024)) + ' GB' : 'Unlimited'}`"></span>
                    </div>
                    <div class="w-full bg-secondary rounded-full h-2.5 overflow-hidden">
                        <div class="bg-primary h-2.5 rounded-full transition-all duration-300"
                             :style="`width: ${Math.min(user?.storage?.percent_used || 0, 100)}%`"></div>
                    </div>
                    <div class="text-[11px] text-muted-foreground flex justify-between">
                        <span>Active: <span x-text="Math.round((user?.storage?.active_bytes || 0) / (1024*1024))">0</span> MB</span>
                        <span>Trash: <span x-text="Math.round((user?.storage?.trash_bytes || 0) / (1024*1024))">0</span> MB</span>
                        <span x-text="`${user?.storage?.percent_used || 0}% used`"></span>
                    </div>
                </div>

                <!-- Quick Action Toggles -->
                <div class="pt-2 flex flex-wrap gap-3">
                    <form method="POST" :action="`/admin/users/${user?.id}/status`">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit"
                                class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-border hover:bg-secondary transition-colors cursor-pointer"
                                x-text="user?.is_active ? 'Suspend Account' : 'Activate Account'">
                        </button>
                    </form>
                </div>
            </div>

            <!-- TAB 2: User Galleries & Moderation -->
            <div x-show="!isLoading && activeTab === 'galleries'" class="space-y-4">
                <template x-if="galleries.length === 0">
                    <div class="py-12 text-center text-muted-foreground text-xs">
                        This photographer has not created any client galleries yet.
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="gal in galleries" :key="gal.uuid">
                        <div class="p-4 rounded-xl border border-border bg-card shadow-xs hover:border-primary/40 transition-colors space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-12 h-12 rounded-lg bg-secondary border border-border overflow-hidden shrink-0 flex items-center justify-center">
                                        <template x-if="gal.cover_url">
                                            <img :src="gal.cover_url" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!gal.cover_url">
                                            <span class="text-xs text-muted-foreground">No img</span>
                                        </template>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs text-foreground truncate" x-text="gal.title"></div>
                                        <div class="text-[11px] text-muted-foreground font-mono truncate" x-text="`/g/${gal.slug}`"></div>
                                        <div class="text-[11px] text-muted-foreground mt-0.5">
                                            <span x-text="`${gal.photo_count} photos`"></span> &bull;
                                            <span x-text="`${Math.round(gal.total_bytes / (1024*1024))} MB`"></span> &bull;
                                            <span x-text="gal.created_at"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <!-- Visibility Badge -->
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                          :class="{
                                              'bg-primary/10 text-primary': gal.visibility === 'public',
                                              'bg-amber-500/10 text-amber-600': gal.visibility === 'password',
                                              'bg-muted text-muted-foreground': gal.visibility === 'private'
                                          }"
                                          x-text="gal.visibility">
                                    </span>

                                    <!-- Moderation Badge -->
                                    <template x-if="gal.is_taken_down">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-destructive text-destructive-foreground uppercase">
                                            Taken Down
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- Moderation Actions -->
                            <div class="flex items-center justify-between pt-2 border-t border-border/60 text-xs">
                                <a :href="`/admin/galleries/${gal.uuid}/preview`"
                                   class="font-bold text-primary hover:underline flex items-center gap-1">
                                    <span>🛡 Preview as Admin (Bypass)</span>
                                    <span>&nearr;</span>
                                </a>

                                <div class="flex items-center gap-2">
                                    <form method="POST" :action="`/admin/galleries/${gal.uuid}/visibility`">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="_method" value="PATCH">
                                        <button type="submit" class="text-muted-foreground hover:text-foreground text-xs font-semibold cursor-pointer"
                                                x-text="gal.visibility === 'public' ? 'Make Private' : 'Make Public'">
                                        </button>
                                    </form>

                                    <template x-if="gal.is_taken_down">
                                        <form method="POST" :action="`/admin/galleries/${gal.uuid}/restore`" onsubmit="return confirm('Restore this gallery?');">
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                            <button type="submit" class="text-green-600 hover:underline font-bold text-xs cursor-pointer">
                                                Restore
                                            </button>
                                        </form>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- TAB 3: Activity Timeline -->
            <div x-show="!isLoading && activeTab === 'activities'" class="space-y-4">
                <template x-if="activities.length === 0">
                    <div class="py-12 text-center text-muted-foreground text-xs">
                        No recorded activity logs or audit events found for this account.
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="item in activities" :key="item.id">
                        <div class="p-3.5 rounded-xl border border-border bg-card space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-foreground" x-text="item.description"></span>
                                <span class="text-[11px] text-muted-foreground font-mono" x-text="item.created_at_human"></span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                <span>Actor: <strong class="text-foreground" x-text="item.actor_name"></strong> (<span x-text="item.actor_role"></span>)</span>
                                <span>&bull;</span>

                                <!-- Masked IP with Secure Reveal -->
                                <span class="flex items-center gap-1">
                                    <span>IP:</span>
                                    <span class="font-mono text-foreground" x-text="revealedIps[item.audit_log_id] || item.ip_masked"></span>
                                    <template x-if="item.can_reveal_ip && !revealedIps[item.audit_log_id]">
                                        <button type="button"
                                                @click="revealIp(item.audit_log_id)"
                                                class="text-primary hover:underline text-[10px] font-bold cursor-pointer ml-1">
                                            Reveal
                                        </button>
                                    </template>
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- TAB 4: Admin Notes & Danger Zone -->
            <div x-show="!isLoading && activeTab === 'notes'" class="space-y-6">

                <!-- Add Note Form -->
                <div class="p-4 rounded-xl border border-border bg-secondary/20 space-y-3">
                    <h4 class="text-xs font-bold text-foreground">Add Internal Administrative Note</h4>
                    <form @submit.prevent="submitNote()" class="space-y-2">
                        <textarea x-model="newNoteContent"
                                  rows="2"
                                  required
                                  placeholder="Record administrative observations, support context, or billing inquiries..."
                                  class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none"></textarea>
                        <div class="flex justify-end">
                            <button type="submit"
                                    :disabled="!newNoteContent.trim() || submittingNote"
                                    class="px-3.5 py-1.5 rounded-lg bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-xs disabled:opacity-50 transition-all cursor-pointer">
                                <span x-text="submittingNote ? 'Saving note...' : 'Post Admin Note'"></span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Existing Notes Thread -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Historical Notes</h4>
                    <template x-if="notes.length === 0">
                        <div class="py-6 text-center text-muted-foreground text-xs">
                            No internal administrative notes recorded yet.
                        </div>
                    </template>
                    <template x-for="note in notes" :key="note.id">
                        <div class="p-3.5 rounded-xl border border-border bg-card space-y-1 text-xs">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span class="font-bold text-foreground" x-text="note.admin_name"></span>
                                <span x-text="note.created_at_human"></span>
                            </div>
                            <div class="text-foreground leading-relaxed whitespace-pre-wrap" x-text="note.content"></div>
                        </div>
                    </template>
                </div>

                <!-- Danger Zone: Account Deletion -->
                <div class="p-4 rounded-xl border border-destructive/30 bg-destructive/5 space-y-3">
                    <div class="text-xs font-bold text-destructive flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Danger Zone: Account Deletion
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Soft-deleting this account immediately prevents login and disables all galleries. Underlying photograph assets and payment histories are safely retained in accordance with storage lifecycle policies.
                    </p>
                    <form method="POST" :action="`/admin/users/${user?.id}`" onsubmit="return confirm('Are you sure you want to soft-delete this user account? All their galleries will become inaccessible.');">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-destructive hover:bg-destructive/90 text-destructive-foreground text-xs font-bold shadow-xs transition-colors cursor-pointer">
                            Soft-Delete User Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Unverify Modal -->
    <div x-show="confirmUnverifyModal"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-foreground/50 backdrop-blur-xs"
         style="display: none;">
        <div @click.away="confirmUnverifyModal = false" class="bg-card border border-border rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <h3 class="text-sm font-bold text-foreground">Confirm Email Unverification</h3>
            <p class="text-xs text-muted-foreground">
                Marking this user's email as unverified may restrict their access to certain features until they re-verify. Are you sure?
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="confirmUnverifyModal = false" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold">Cancel</button>
                <button type="button" @click="unverifyEmail()" class="px-3 py-1.5 rounded-lg bg-destructive text-destructive-foreground font-bold text-xs">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
function userDetailDrawer() {
    return {
        isOpen: false,
        isLoading: false,
        activeTab: 'overview',
        user: null,
        galleries: [],
        activities: [],
        notes: [],
        revealedIps: {},
        newNoteContent: '',
        submittingNote: false,
        errorMessage: '',
        confirmUnverifyModal: false,

        openUser(id) {
            this.isOpen = true;
            this.isLoading = true;
            this.activeTab = 'overview';
            this.errorMessage = '';
            this.revealedIps = {};

            fetch(`/admin/users/${id}/details`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Failed to load user profile');
                return res.json();
            })
            .then(data => {
                this.user = data.user;
                this.galleries = data.galleries || [];
                this.activities = data.activities || [];
                this.notes = data.notes || [];
                this.isLoading = false;
            })
            .catch(err => {
                this.errorMessage = err.message;
                this.isLoading = false;
            });
        },

        close() {
            this.isOpen = false;
            this.user = null;
        },

        verifyEmail() {
            if (!this.user) return;
            fetch(`/admin/users/${this.user.id}/email/verify`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.user.is_email_verified = true;
                this.user.email_verified_at = 'Just now';
            });
        },

        unverifyEmail() {
            if (!this.user) return;
            this.confirmUnverifyModal = false;
            fetch(`/admin/users/${this.user.id}/email/unverify`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.user.is_email_verified = false;
                this.user.email_verified_at = null;
            });
        },

        resendVerificationEmail() {
            if (!this.user) return;
            fetch(`/admin/users/${this.user.id}/email/resend`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'Verification email dispatched.');
            });
        },

        triggerPasswordReset() {
            if (!this.user) return;
            fetch(`/admin/users/${this.user.id}/password-reset`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'Password reset link sent.');
            });
        },

        revealIp(auditLogId) {
            if (!auditLogId) return;
            fetch(`/admin/audit-logs/${auditLogId}/reveal-ip`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.revealedIps[auditLogId] = data.ip;
            });
        },

        submitNote() {
            if (!this.newNoteContent.trim() || !this.user) return;
            this.submittingNote = true;
            fetch(`/admin/users/${this.user.id}/notes`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ content: this.newNoteContent })
            })
            .then(res => res.json())
            .then(data => {
                this.notes.unshift(data.note);
                this.newNoteContent = '';
                this.submittingNote = false;
            })
            .catch(() => {
                this.submittingNote = false;
            });
        }
    };
}
</script>
