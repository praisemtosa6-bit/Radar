@extends('layouts.app')

@section('content')
<div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-100 p-8" x-data="{ isLogin: true }">
    <div class="mb-10 text-center">
        <h1 class="text-3xl font-bold text-slate-800 mb-2" x-text="isLogin ? 'Welcome Back' : 'Create Account'">Welcome Back</h1>
        <p class="text-slate-500" x-text="isLogin ? 'Sign in to continue to Radar' : 'Sign up to get started with Radar'">Sign in to continue to Radar</p>
    </div>

    <form action="/setup" method="GET" class="space-y-4">
        <div x-show="!isLogin">
            <input type="text" placeholder="Full Name" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
        </div>
        <div>
            <input type="email" placeholder="Email Address" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
        </div>
        <div>
            <input type="password" placeholder="Password" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-indigo-200 transition-all mt-4">
            <span x-text="isLogin ? 'Sign In' : 'Sign Up'">Sign In</span>
        </button>
    </form>

    <div class="mt-8 text-center text-slate-500">
        <span x-text="isLogin ? 'Don\'t have an account? ' : 'Already have an account? '">Don't have an account? </span>
        <button type="button" @click="isLogin = !isLogin" class="text-indigo-600 font-semibold hover:underline" x-text="isLogin ? 'Sign Up' : 'Sign In'">Sign Up</button>
    </div>
</div>

<!-- Add Alpine.js for simple interactions -->
<script src="//unpkg.com/alpinejs" defer></script>
@endsection
