<template>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-gray-100 flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-600 mb-4">
                    <span class="text-white font-bold text-lg">jr</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Create account</h1>
                <p class="text-gray-500 text-sm mt-1">Set up your jr-developer instance</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            autofocus
                            placeholder="Your name"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            :class="form.errors.name ? 'border-red-300' : 'border-gray-200'"
                        />
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            :class="form.errors.email ? 'border-red-300' : 'border-gray-200'"
                        />
                        <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Min. 8 characters"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            :class="form.errors.password ? 'border-red-300' : 'border-gray-200'"
                        />
                        <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Confirm password</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Repeat password"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating account…' : 'Create account' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-gray-500 mt-6">
                Already have an account?
                <Link :href="route('login')" class="text-indigo-600 hover:underline font-medium">Sign in</Link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>
