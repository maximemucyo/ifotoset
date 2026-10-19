{{-- User detail slide-over drawer. Opened with $dispatch('open-user-drawer', { id }) --}}
<div x-data="userDetailDrawer()"
     @open-user-drawer.window="openUser($event.detail.id)"
     @keydown.escape.window="if (isOpen && !confirmUnverifyModal) close()"
     class="relative z-50">

    <!-- Backdrop -->
    <div x-show="isOpen"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-[2px]"
         style="display: none;"></div>

    <!-- Slide-over panel -->
    <aside x-show="isOpen"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         role="dialog" aria-modal="true" aria-label="User details"
         class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col overflow-hidden border-l border-border bg-background shadow-2xl sm:inset-y-2 sm:right-2 sm:rounded-2xl sm:border"
         style="display: none;">

        <!-- Header -->
        <header class="shrink-0 border-b border-border bg-card px-5 pt-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3.5">
                    <span class="avatar size-12 text-base uppercase" :style="avatarStyle()" x-text="initials()"></span>
                    <div class="min-w-0">
                        <template x-if="user">
                            <h2 class="truncate text-[17px] font-semibold tracking-tight text-foreground" x-text="user.name"></h2>
                        </template>
                        <template x-if="!user">
                            <div class="skeleton h-5 w-40"></div>
                        </template>
                        <p class="mt-0.5 truncate text-[13px] text-muted-foreground" x-text="user?.email || ''"></p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5" x-show="user">
                            <span x-show="user?.role === 'admin'" class="badge badge-brand"><x-icon name="shield" /> Admin</span>
                            <span x-show="user?.role !== 'admin'" class="badge badge-neutral">Photographer</span>
                            <span class="badge badge-dot" :class="user?.is_active ? 'badge-success' : 'badge-danger'" x-text="user?.is_active ? 'Active' : 'Suspended'"></span>
                            <span class="badge" :class="user?.is_email_verified ? 'badge-success' : 'badge-warning'">
                                <x-icon name="mail" />
                                <span x-text="user?.is_email_verified ? 'Verified' : 'Unverified'"></span>
                            </span>
                            <span class="badge badge-neutral" x-text="user?.plan_name || 'Free'"></span>
                        </div>
                    </div>
                </div>
                <button type="button" @click="close()" class="btn btn-ghost btn-icon btn-sm -mr-1.5 -mt-1 shrink-0" aria-label="Close panel">
                    <x-icon name="x" />
                </button>
            </div>

            <!-- Primary actions -->
            <div class="mt-4 flex flex-wrap items-center gap-2" x-show="user">
                <template x-if="user && user.role !== 'admin' && user.is_active">
                    <form method="POST" :action="`/admin/users/${user.id}/impersonate`">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-icon name="log-in" /> Impersonate
                        </button>
                    </form>
                </template>
                <template x-if="user?.public_url && user?.username">
                    <a :href="user.public_url" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                        <x-icon name="globe" /> Portfolio <x-icon name="arrow-up-right" class="!size-3" />
                    </a>
                </template>
                <button type="button" @click="$clipboard(user?.email || '', 'Email copied')" class="btn btn-ghost btn-sm">
                    <x-icon name="copy" /> Copy email
                </button>
            </div>

            <!-- Tabs -->
            <nav class="tabs-line -mx-5 mt-4 gap-5 overflow-x-auto px-5" role="tablist">
                <button type="button" role="tab" @click="activeTab = 'overview'" :aria-selected="activeTab === 'overview'" class="tab-line whitespace-nowrap text-[13px]">
                    Overview
                </button>
                <button type="button" role="tab" @click="activeTab = 'galleries'" :aria-selected="activeTab === 'galleries'" class="tab-line whitespace-nowrap text-[13px]">
                    Galleries <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground" x-text="galleries.length">0</span>
                </button>
                <button type="button" role="tab" @click="activeTab = 'activities'" :aria-selected="activeTab === 'activities'" class="tab-line whitespace-nowrap text-[13px]">
                    Activity <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground" x-text="activities.length">0</span>
                </button>
                <button type="button" role="tab" @click="activeTab = 'notes'" :aria-selected="activeTab === 'notes'" class="tab-line whitespace-nowrap text-[13px]">
                    Notes <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground" x-text="notes.length">0</span>
                </button>
            </nav>
        </header>

        <!-- Body -->
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">

            <!-- Loading skeleton -->
            <div x-show="isLoading" class="space-y-4" aria-live="polite">
                <span class="sr-only">Loading user record</span>
                <div class="skeleton h-28 w-full rounded-xl"></div>
                <div class="skeleton h-20 w-full rounded-xl"></div>
                <div class="skeleton h-36 w-full rounded-xl"></div>
            </div>

            <!-- Error -->
            <div x-show="errorMessage" class="alert alert-danger" style="display: none;">
                <x-icon name="circle-alert" />
                <span x-text="errorMessage"></span>
            </div>

            <!-- TAB: Overview -->
            <div x-show="!isLoading && !errorMessage && activeTab === 'overview'" class="space-y-5">

                <!-- Account details -->
                <section class="card">
                    <h3 class="border-b border-border px-4 py-3 text-[13px] font-semibold text-foreground">Account</h3>
                    <dl class="grid grid-cols-1 divide-y divide-border text-[13px] sm:grid-cols-2 sm:divide-y-0">
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:border-b sm:border-border">
                            <dt class="text-muted-foreground">Username</dt>
                            <dd class="truncate font-mono text-xs text-foreground" x-text="user?.username ? `@${user.username}` : 'None assigned'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:border-b sm:border-l sm:border-border">
                            <dt class="text-muted-foreground">Plan</dt>
                            <dd class="font-medium text-foreground" x-text="user?.plan_name || 'Free'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:border-b sm:border-border">
                            <dt class="text-muted-foreground">Status</dt>
                            <dd class="font-medium" :class="user?.is_active ? 'text-success' : 'text-destructive'" x-text="user?.is_active ? 'Active' : 'Suspended'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:border-b sm:border-l sm:border-border">
                            <dt class="text-muted-foreground">Joined</dt>
                            <dd class="text-foreground" x-text="user?.joined_at || '-'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd class="truncate text-foreground" :class="!user?.phone && 'text-subtle'" x-text="user?.phone || 'Not provided'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:border-l sm:border-border">
                            <dt class="text-muted-foreground">Location</dt>
                            <dd class="truncate text-foreground" :class="!user?.location && 'text-subtle'" x-text="user?.location || 'Not provided'"></dd>
                        </div>
                    </dl>
                </section>

                <!-- Usage -->
                <section class="card p-4">
                    <h3 class="text-[13px] font-semibold text-foreground">Usage</h3>
                    <div class="mt-4 space-y-5">
                        <div>
                            <div class="flex items-center justify-between gap-3 text-[13px]">
                                <span class="flex items-center gap-2 text-muted-foreground"><x-icon name="hard-drive" class="size-4" /> Storage</span>
                                <span class="font-mono text-xs text-foreground" x-text="`${Math.round((user?.storage?.used_bytes || 0) / (1024*1024))} MB / ${user?.storage?.limit_bytes ? Math.round(user.storage.limit_bytes / (1024*1024*1024)) + ' GB' : 'Unlimited'}`"></span>
                            </div>
                            <div class="progress mt-2 !h-2">
                                <span :class="(user?.storage?.percent_used || 0) >= 90 ? '!bg-destructive' : ((user?.storage?.percent_used || 0) >= 75 ? '!bg-warning' : '')"
                                      :style="`width: ${Math.min(user?.storage?.percent_used || 0, 100)}%`"></span>
                            </div>
                            <div class="mt-1.5 flex justify-between text-[11px] text-muted-foreground">
                                <span>Active <span class="tabular-nums text-foreground" x-text="Math.round((user?.storage?.active_bytes || 0) / (1024*1024))">0</span> MB</span>
                                <span>Trash <span class="tabular-nums text-foreground" x-text="Math.round((user?.storage?.trash_bytes || 0) / (1024*1024))">0</span> MB</span>
                                <span class="tabular-nums" x-text="`${user?.storage?.percent_used || 0}% used`"></span>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between gap-3 text-[13px]">
                                <span class="flex items-center gap-2 text-muted-foreground"><x-icon name="video" class="size-4" /> Video hosting</span>
                                <span class="font-mono text-xs text-foreground" x-text="user?.video?.formatted || 'No video support'"></span>
                            </div>
                            <div class="progress mt-2 !h-2">
                                <span :style="`width: ${Math.min(user?.video?.percent_used || 0, 100)}%`"></span>
                            </div>
                            <div class="mt-1.5 flex justify-between text-[11px] text-muted-foreground">
                                <span>Used <span class="text-foreground" x-text="user?.video?.used_formatted || '0m'"></span></span>
                                <span>Reserved <span class="text-foreground" x-text="user?.video?.reserved_formatted || '0s'"></span></span>
                                <span class="tabular-nums" x-text="`${user?.video?.percent_used || 0}% used`"></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Email & security -->
                <section class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-[13px] font-semibold text-foreground">Email &amp; security</h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                <template x-if="user?.is_email_verified">
                                    <span>Verified <span x-text="user?.email_verified_at"></span></span>
                                </template>
                                <template x-if="user && !user.is_email_verified">
                                    <span>This email address has not been verified yet.</span>
                                </template>
                            </p>
                        </div>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                              :class="user?.is_email_verified ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'">
                            <x-icon name="shield-check" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <template x-if="user && !user.is_email_verified">
                            <button type="button" @click="verifyEmail()" :disabled="busy === 'verify'" class="btn btn-primary btn-sm">
                                <x-icon name="loader-circle" class="animate-spin" x-show="busy === 'verify'" />
                                <x-icon name="circle-check" x-show="busy !== 'verify'" />
                                Mark as verified
                            </button>
                        </template>
                        <template x-if="user?.is_email_verified">
                            <button type="button" @click="confirmUnverifyModal = true" class="btn btn-outline btn-sm">
                                <x-icon name="circle-x" /> Mark as unverified
                            </button>
                        </template>
                        <template x-if="user && !user.is_email_verified">
                            <button type="button" @click="resendVerificationEmail()" :disabled="busy === 'resend'" class="btn btn-outline btn-sm">
                                <x-icon name="loader-circle" class="animate-spin" x-show="busy === 'resend'" />
                                <x-icon name="send" x-show="busy !== 'resend'" />
                                Resend verification
                            </button>
                        </template>
                        <button type="button" @click="triggerPasswordReset()" :disabled="busy === 'reset'" class="btn btn-outline btn-sm">
                            <x-icon name="loader-circle" class="animate-spin" x-show="busy === 'reset'" />
                            <x-icon name="key" x-show="busy !== 'reset'" />
                            Send password reset
                        </button>
                    </div>
                </section>

                <!-- Access -->
                <section class="card divide-y divide-border">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-[13px] font-semibold text-foreground">Account access</h3>
                            <p class="mt-0.5 text-xs text-muted-foreground" x-text="user?.is_active ? 'Suspending blocks sign-in and disables their galleries.' : 'This account is suspended and cannot sign in.'"></p>
                        </div>
                        <form method="POST" :action="`/admin/users/${user?.id}/status`"
                              @submit="if (!confirm(user?.is_active ? 'Suspend this account?' : 'Activate this account?')) $event.preventDefault()">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <button type="submit" class="btn btn-sm whitespace-nowrap" :class="user?.is_active ? 'btn-danger-ghost border border-destructive/30' : 'btn-primary'">
                                <span x-text="user?.is_active ? 'Suspend Account' : 'Activate Account'"></span>
                            </button>
                        </form>
                    </div>
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-[13px] font-semibold text-foreground">Role</h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">Admins can access this console.</p>
                        </div>
                        <form method="POST" :action="`/admin/users/${user?.id}/role`" class="flex items-center gap-2"
                              @submit="if (!confirm('Change this user\'s role?')) $event.preventDefault()">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <select name="role" class="select input-sm w-36" aria-label="Role">
                                <option value="user" :selected="user?.role !== 'admin'">Photographer</option>
                                <option value="admin" :selected="user?.role === 'admin'">Admin</option>
                            </select>
                            <button type="submit" class="btn btn-outline btn-sm">Save</button>
                        </form>
                    </div>
                </section>
            </div>

            <!-- TAB: Galleries -->
            <div x-show="!isLoading && !errorMessage && activeTab === 'galleries'" class="space-y-3">
                <template x-if="galleries.length === 0">
                    <div class="rounded-xl border border-dashed border-border-strong/70 px-6 py-12 text-center">
                        <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground"><x-icon name="images" class="size-4" /></div>
                        <p class="text-[13px] font-medium text-foreground">No galleries yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">This photographer has not created any client galleries.</p>
                    </div>
                </template>

                <template x-for="gal in galleries" :key="gal.uuid">
                    <div class="card overflow-hidden transition-colors hover:border-border-strong">
                        <div class="flex items-start gap-3 p-3">
                            <div class="flex aspect-[4/3] w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted text-muted-foreground">
                                <template x-if="gal.cover_url">
                                    <img :src="gal.cover_url" alt="" class="size-full object-cover" loading="lazy" decoding="async">
                                </template>
                                <template x-if="!gal.cover_url">
                                    <x-icon name="image" class="size-4" />
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-[13px] font-medium text-foreground" x-text="gal.title"></p>
                                    <div class="flex shrink-0 gap-1">
                                        <span class="badge capitalize"
                                              :class="{
                                                  'badge-success': gal.visibility === 'public',
                                                  'badge-warning': gal.visibility === 'password',
                                                  'badge-neutral': gal.visibility === 'private'
                                              }"
                                              x-text="gal.visibility === 'password' ? 'PIN' : gal.visibility"></span>
                                        <template x-if="gal.is_taken_down">
                                            <span class="badge badge-danger">Taken down</span>
                                        </template>
                                    </div>
                                </div>
                                <p class="truncate font-mono text-[11px] text-muted-foreground" x-text="`/g/${gal.slug}`"></p>
                                <p class="mt-1 text-[11px] text-muted-foreground">
                                    <span x-text="`${gal.photo_count} photos`"></span> ·
                                    <span x-text="`${Math.round(gal.total_bytes / (1024*1024))} MB`"></span> ·
                                    <span x-text="gal.created_at"></span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2 border-t border-border bg-muted/30 px-3 py-2">
                            <a :href="`/admin/galleries/${gal.uuid}/preview`" class="btn btn-ghost btn-xs">
                                <x-icon name="shield" /> Admin preview
                            </a>
                            <div class="flex items-center gap-1">
                                <form method="POST" :action="`/admin/galleries/${gal.uuid}/visibility`">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit" class="btn btn-ghost btn-xs">
                                        <x-icon name="eye-off" x-show="gal.visibility === 'public'" />
                                        <x-icon name="eye" x-show="gal.visibility !== 'public'" />
                                        <span x-text="gal.visibility === 'public' ? 'Make Private' : 'Make Public'"></span>
                                    </button>
                                </form>
                                <template x-if="gal.is_taken_down">
                                    <form method="POST" :action="`/admin/galleries/${gal.uuid}/restore`" onsubmit="return confirm('Restore this gallery?');">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <button type="submit" class="btn btn-outline btn-xs text-success">
                                            <x-icon name="rotate-ccw" /> Restore
                                        </button>
                                    </form>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- TAB: Activity -->
            <div x-show="!isLoading && !errorMessage && activeTab === 'activities'">
                <template x-if="activities.length === 0">
                    <div class="rounded-xl border border-dashed border-border-strong/70 px-6 py-12 text-center">
                        <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground"><x-icon name="history" class="size-4" /></div>
                        <p class="text-[13px] font-medium text-foreground">No activity recorded</p>
                        <p class="mt-1 text-xs text-muted-foreground">No activity logs or audit events found for this account.</p>
                    </div>
                </template>

                <ol class="relative space-y-1">
                    <template x-for="item in activities" :key="item.id">
                        <li class="relative flex gap-3 pb-3 last:pb-0">
                            <div class="relative flex w-6 shrink-0 justify-center">
                                <span class="absolute top-6 bottom-[-0.75rem] w-px bg-border [li:last-child_&]:hidden"></span>
                                <span class="relative mt-1 flex size-6 items-center justify-center rounded-full border border-border bg-card"
                                      :class="item.source === 'admin_audit' ? 'text-brand' : 'text-muted-foreground'">
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                </span>
                            </div>
                            <div class="min-w-0 flex-1 rounded-lg border border-border bg-card px-3 py-2.5">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-[13px] font-medium text-foreground" x-text="item.description"></p>
                                    <time class="shrink-0 text-[11px] text-muted-foreground" :datetime="item.created_at" x-text="item.created_at_human"></time>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span><span class="text-foreground" x-text="item.actor_name"></span> (<span x-text="item.actor_role"></span>)</span>
                                    <span class="flex items-center gap-1">
                                        <span>IP</span>
                                        <span class="font-mono text-foreground" x-text="revealedIps[item.audit_log_id] || item.ip_masked"></span>
                                        <template x-if="item.can_reveal_ip && !revealedIps[item.audit_log_id]">
                                            <button type="button" @click="revealIp(item.audit_log_id)" class="ml-0.5 inline-flex items-center gap-0.5 font-medium text-brand hover:underline">
                                                <x-icon name="eye" class="size-3" /> Reveal
                                            </button>
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>

            <!-- TAB: Notes & danger zone -->
            <div x-show="!isLoading && !errorMessage && activeTab === 'notes'" class="space-y-5">
                <form @submit.prevent="submitNote()" class="card overflow-hidden">
                    <label for="admin-note" class="sr-only">Internal note</label>
                    <textarea id="admin-note"
                              x-model="newNoteContent"
                              rows="3"
                              required
                              placeholder="Add an internal note: support context, billing inquiries, observations…"
                              class="block w-full resize-none border-0 bg-transparent px-4 py-3 text-[13px] text-foreground placeholder:text-subtle focus:outline-none focus:ring-0"></textarea>
                    <div class="flex items-center justify-between gap-3 border-t border-border bg-muted/40 px-3 py-2">
                        <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground"><x-icon name="lock" class="size-3" /> Only visible to admins</span>
                        <button type="submit" :disabled="!newNoteContent.trim() || submittingNote" class="btn btn-primary btn-xs">
                            <x-icon name="loader-circle" class="animate-spin" x-show="submittingNote" />
                            <span x-text="submittingNote ? 'Saving note...' : 'Post note'"></span>
                        </button>
                    </div>
                </form>

                <div>
                    <h4 class="mb-3 text-xs font-medium uppercase tracking-wider text-subtle">History</h4>
                    <template x-if="notes.length === 0">
                        <p class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-xs text-muted-foreground">No internal notes recorded yet.</p>
                    </template>
                    <div class="space-y-2.5">
                        <template x-for="note in notes" :key="note.id">
                            <div class="rounded-lg border border-border bg-card px-4 py-3">
                                <div class="flex items-center justify-between gap-3 text-[11px] text-muted-foreground">
                                    <span class="font-medium text-foreground" x-text="note.admin_name"></span>
                                    <span :title="note.created_at" x-text="note.created_at_human"></span>
                                </div>
                                <p class="mt-1.5 whitespace-pre-wrap text-[13px] leading-relaxed text-foreground" x-text="note.content"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Danger zone -->
                <section class="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
                    <h4 class="flex items-center gap-2 text-[13px] font-semibold text-destructive">
                        <x-icon name="triangle-alert" class="size-4" /> Danger zone
                    </h4>
                    <p class="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                        Soft-deleting this account immediately prevents login and disables all galleries. Photo assets and payment history are retained according to storage lifecycle policies.
                    </p>
                    <form method="POST" :action="`/admin/users/${user?.id}`" class="mt-3" onsubmit="return confirm('Are you sure you want to soft-delete this user account? All their galleries will become inaccessible.');">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <x-icon name="trash-2" /> Delete account
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </aside>

    <!-- Confirm unverify modal -->
    <div x-show="confirmUnverifyModal"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4"
         @keydown.escape.window="confirmUnverifyModal = false"
         role="alertdialog" aria-modal="true"
         style="display: none;">
        <div x-show="confirmUnverifyModal" x-transition.opacity class="absolute inset-0 bg-black/40"></div>
        <div x-show="confirmUnverifyModal" x-transition @click.away="confirmUnverifyModal = false" class="relative w-full max-w-sm max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-card p-5 shadow-2xl">
            <div class="flex gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-warning/10 text-warning"><x-icon name="triangle-alert" class="size-4" /></span>
                <div>
                    <h3 class="text-[15px] font-semibold text-foreground">Mark email as unverified?</h3>
                    <p class="mt-1 text-[13px] text-muted-foreground">The user may lose access to some features until they verify again.</p>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="confirmUnverifyModal = false" class="btn btn-ghost btn-sm">Cancel</button>
                <button type="button" @click="unverifyEmail()" class="btn btn-danger btn-sm">Mark unverified</button>
            </div>
        </div>
    </div>
</div>

<script>
function userDetailDrawer() {
    const notify = (message, type = 'success') => {
        if (typeof window.toast === 'function') window.toast(message, type);
        else alert(message);
    };

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
        busy: '',

        // Mirrors the Blade avatar component: initials from first + last word, hue from crc32(name)
        initials() {
            const w = (this.user?.name || '').trim().split(/\s+/).filter(Boolean);
            if (!w.length) return '';
            return (w[0][0] + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase();
        },
        avatarStyle() {
            const name = this.user?.name || '';
            if (!name) return '';
            let c = -1;
            for (const byte of new TextEncoder().encode(name)) {
                c ^= byte;
                for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xEDB88320 & -(c & 1));
            }
            const hues = [18, 32, 200, 262, 152, 340, 220, 45];
            const h = hues[((c ^ -1) >>> 0) % hues.length];
            return `background: hsl(${h} 70% 92%); color: hsl(${h} 45% 30%)`;
        },

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
            this.busy = 'verify';
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
                notify(data.message || 'Email marked as verified.');
            })
            .finally(() => { this.busy = ''; });
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
                notify(data.message || 'Email marked as unverified.', 'info');
            });
        },

        resendVerificationEmail() {
            if (!this.user) return;
            this.busy = 'resend';
            fetch(`/admin/users/${this.user.id}/email/resend`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                notify(data.message || 'Verification email dispatched.');
            })
            .finally(() => { this.busy = ''; });
        },

        triggerPasswordReset() {
            if (!this.user) return;
            this.busy = 'reset';
            fetch(`/admin/users/${this.user.id}/password-reset`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                notify(data.message || 'Password reset link sent.');
            })
            .finally(() => { this.busy = ''; });
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
