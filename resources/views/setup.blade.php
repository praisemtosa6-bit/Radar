@extends('layouts.app')

@section('content')
<div class="w-full max-w-2xl bg-white rounded-2xl shadow-sm border border-slate-100 p-8" x-data="setupForm()">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 mb-2">Let's customize your experience</h1>
        <p class="text-slate-500">Tell us a bit about yourself so we can tailor Radar to your needs.</p>
    </div>

    <form action="/preferences" method="GET" x-ref="form" @submit.prevent="submitForm">
        <!-- Hidden inputs to pass data -->
        <input type="hidden" name="role" x-model="selectedRole">
        <input type="hidden" name="interests" x-model="selectedInterests.join(',')">

        <!-- Role Selection -->
        <div class="mb-10">
            <h2 class="text-xl font-semibold text-slate-800 mb-4">What best describes you?</h2>
            <div class="flex flex-wrap gap-3">
                <template x-for="role in roles" :key="role">
                    <button type="button" 
                        @click="selectedRole = role"
                        :class="selectedRole === role ? 'bg-indigo-50 border-indigo-600 text-indigo-700 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-600'"
                        class="px-5 py-2.5 rounded-full border transition-all hover:bg-indigo-50 hover:border-indigo-300"
                        x-text="role">
                    </button>
                </template>
            </div>
        </div>

        <!-- Interests Selection -->
        <div class="mb-10">
            <h2 class="text-xl font-semibold text-slate-800 mb-1">What are you looking for?</h2>
            <p class="text-sm text-slate-500 mb-4">Select all that apply</p>
            
            <div class="space-y-3">
                <template x-for="interest in interests" :key="interest.id">
                    <div 
                        @click="toggleInterest(interest.id)"
                        :class="selectedInterests.includes(interest.id) ? 'border-indigo-600 bg-slate-50' : 'border-transparent bg-white'"
                        class="flex items-center justify-between p-5 rounded-2xl border-2 shadow-sm cursor-pointer transition-all hover:border-indigo-300">
                        
                        <div>
                            <h3 
                                :class="selectedInterests.includes(interest.id) ? 'text-indigo-700' : 'text-slate-800'"
                                class="text-lg font-semibold mb-1" x-text="interest.label"></h3>
                            <p class="text-sm text-slate-500" x-text="interest.description"></p>
                        </div>

                        <!-- Checkbox -->
                        <div 
                            :class="selectedInterests.includes(interest.id) ? 'border-indigo-600' : 'border-slate-300'"
                            class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors">
                            <div x-show="selectedInterests.includes(interest.id)" class="w-3 h-3 bg-indigo-600 rounded-full"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <button type="submit" 
            :disabled="!selectedRole || selectedInterests.length === 0"
            :class="!selectedRole || selectedInterests.length === 0 ? 'bg-slate-300 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200'"
            class="w-full text-white font-semibold py-4 px-4 rounded-xl transition-all text-lg">
            Continue
        </button>
    </form>
</div>

<!-- Add Alpine.js for simple interactions -->
<script src="//unpkg.com/alpinejs" defer></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('setupForm', () => ({
            roles: ['Student', 'Freelancer', 'Business', 'Professional'],
            selectedRole: null,
            interests: [
                { id: 'scholarships', label: 'Scholarships', description: 'Find educational funding (Free)' },
                { id: 'gigs', label: 'Gigs', description: 'Discover short-term work opportunities' },
                { id: 'tenders', label: 'Tenders', description: 'Bid on larger projects and contracts' },
            ],
            selectedInterests: [],

            toggleInterest(id) {
                if (this.selectedInterests.includes(id)) {
                    this.selectedInterests = this.selectedInterests.filter(i => i !== id);
                } else {
                    this.selectedInterests.push(id);
                }
            },

            submitForm() {
                if (this.selectedRole && this.selectedInterests.length > 0) {
                    this.$refs.form.submit();
                }
            }
        }))
    })
</script>
@endsection
