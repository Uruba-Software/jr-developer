<template>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-gray-100 flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-600 mb-4">
                    <span class="text-white font-bold text-lg">jr</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Welcome back</h1>
                <p class="text-gray-500 text-sm mt-1">Sign in to jr-developer</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            autofocus
                            placeholder="you@example.com"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            :class="errors.email ? 'border-red-300' : 'border-gray-200'"
                        />
                        <p v-if="errors.email" class="text-xs text-red-500 mt-1">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="remember" v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <label for="remember" class="text-sm text-gray-600">Remember me</label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-gray-500 mt-6">
                Don't have an account?
                <Link :href="route('register')" class="text-indigo-600 hover:underline font-medium">Register</Link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    errors: { type: Object, default: () => ({}) },
});

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>
