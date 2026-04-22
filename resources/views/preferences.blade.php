@extends('layouts.app')

@section('content')
<div class="w-full max-w-2xl bg-white rounded-2xl shadow-sm border border-slate-100 p-8" x-data="preferencesForm()">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 mb-2">Narrow it down</h1>
        <p class="text-slate-500">
            You selected <span class="font-semibold text-slate-700" x-text="interestsArray.join(', ')"></span>. What specific fields or types are you interested in?
        </p>
    </div>

    <!-- Search Input -->
    <div class="mb-6 relative">
        <input 
            type="text" 
            x-model="searchQuery"
            placeholder="Search fields (e.g. Technology)" 
            class="w-full px-5 py-4 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-slate-800 text-lg shadow-sm">
        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
    </div>

    <!-- Tags Container -->
    <div class="mb-10">
        <div class="flex flex-wrap gap-3">
            <template x-for="tag in filteredTags" :key="tag">
                <button type="button" 
                    @click="toggleTag(tag)"
                    :class="selectedTags.includes(tag) ? 'bg-indigo-600 border-indigo-600 text-white shadow-md' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                    class="px-4 py-2 rounded-full border transition-all flex items-center gap-2">
                    <span x-show="selectedTags.includes(tag)">✓</span>
                    <span x-show="!selectedTags.includes(tag)">+</span>
                    <span x-text="tag" class="font-medium"></span>
                </button>
            </template>
        </div>

        <div x-show="filteredTags.length === 0" class="text-center py-8 text-slate-500">
            No fields match your search.
        </div>
    </div>

    <form action="/" method="GET">
        <button type="submit" 
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-4 px-4 rounded-xl shadow-lg shadow-indigo-200 transition-all text-lg">
            Complete Setup
        </button>
    </form>
</div>

<!-- Add Alpine.js for simple interactions -->
<script src="//unpkg.com/alpinejs" defer></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('preferencesForm', () => ({
            interestsQueryParam: new URLSearchParams(window.location.search).get('interests') || '',
            get interestsArray() {
                return this.interestsQueryParam ? this.interestsQueryParam.split(',') : [];
            },

            searchQuery: '',
            selectedTags: [],
            availableTags: [
                'Technology', 'Healthcare', 'Education', 'Construction',
                'Creative Arts', 'Finance', 'Engineering', 'Remote Work',
                'Part-Time', 'Full-Time', 'Undergraduate', 'Postgraduate',
                'Government', 'Private Sector', 'Freelance'
            ],

            get filteredTags() {
                if (this.searchQuery === '') {
                    return this.availableTags;
                }
                return this.availableTags.filter(tag => 
                    tag.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            },

            toggleTag(tag) {
                if (this.selectedTags.includes(tag)) {
                    this.selectedTags = this.selectedTags.filter(t => t !== tag);
                } else {
                    this.selectedTags.push(tag);
                }
            }
        }))
    })
</script>
@endsection
