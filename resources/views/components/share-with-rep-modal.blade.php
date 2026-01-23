@props(['call', 'grade'])

<div
    x-data="{
        open: false,
        loading: true,
        sending: false,
        error: null,
        sharingInfo: null,
        
        async fetchSharingInfo() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch('{{ route('manager.calls.sharing-info', $call) }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                    },
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    this.error = data.error || 'Failed to load sharing info';
                    return;
                }
                
                this.sharingInfo = data;
            } catch (err) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        
        async sendFeedback() {
            this.sending = true;
            this.error = null;
            
            try {
                const response = await fetch('{{ route('manager.calls.share', $call) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                    },
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    this.error = data.error || 'Failed to send feedback';
                    return;
                }
                
                // Update local state
                this.sharingInfo.was_shared = true;
                this.sharingInfo.shared_at = data.shared_at;
                this.open = false;
                
                // Show success message
                alert('Feedback sent successfully to ' + this.sharingInfo.rep_email);
            } catch (err) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.sending = false;
            }
        },
        
        openModal() {
            this.open = true;
            this.fetchSharingInfo();
        }
    }"
    class="mt-4"
>
    <!-- Trigger Button -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4">
            <template x-if="!sharingInfo || !sharingInfo.was_shared">
                <button
                    type="button"
                    @click="openModal()"
                    class="w-full py-2.5 px-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-medium transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Share with Rep
                </button>
            </template>
            
            <template x-if="sharingInfo && sharingInfo.was_shared">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Shared <span x-text="sharingInfo.shared_at"></span></span>
                    </div>
                    <button
                        type="button"
                        @click="openModal()"
                        class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                    >
                        Resend
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Modal -->
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        @keydown.escape.window="open = false"
    >
        <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
            <div
                class="bg-white rounded-xl shadow-xl w-full max-w-md pointer-events-auto"
                @click.stop
            >
                <!-- Header -->
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Share Feedback with Rep</h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="px-6 py-4">
                    <!-- Loading State -->
                    <template x-if="loading">
                        <div class="py-8 text-center">
                            <svg class="animate-spin h-8 w-8 mx-auto text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Loading...</p>
                        </div>
                    </template>

                    <!-- Error State -->
                    <template x-if="!loading && error">
                        <div class="py-4 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mb-3">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <p class="text-gray-700" x-text="error"></p>
                        </div>
                    </template>

                    <!-- Loaded State -->
                    <template x-if="!loading && !error && sharingInfo">
                        <div class="space-y-4">
                            <!-- Rep Info -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Rep Name</label>
                                        <p class="text-sm text-gray-900" x-text="sharingInfo.rep_name"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                                        <p class="text-sm text-gray-900" x-text="sharingInfo.rep_email"></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Summary -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Email will include:</h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Overall score and category breakdown
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <span x-text="sharingInfo.note_count + ' coaching note' + (sharingInfo.note_count !== 1 ? 's' : '') + ' with transcript snippets'"></span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Call checkpoints
                                    </li>
                                </ul>
                            </div>

                            <!-- Warning if no notes -->
                            <template x-if="!sharingInfo.has_notes">
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-sm text-amber-800">
                                        No coaching notes added. The email will only include scores and checkpoints.
                                    </p>
                                </div>
                            </template>

                            <!-- Already shared notice -->
                            <template x-if="sharingInfo.was_shared">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2">
                                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-sm text-blue-800">
                                        Previously shared on <span x-text="sharingInfo.shared_at"></span>
                                        <template x-if="sharingInfo.shared_by">
                                            <span> by <span x-text="sharingInfo.shared_by"></span></span>
                                        </template>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t flex justify-end gap-3">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="sendFeedback()"
                        :disabled="loading || error || sending"
                        class="px-4 py-2 text-sm bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <template x-if="sending">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <span x-text="sending ? 'Sending...' : (sharingInfo && sharingInfo.was_shared ? 'Resend Feedback' : 'Send Feedback')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
