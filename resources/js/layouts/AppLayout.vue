<template>
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside class="w-56 flex-shrink-0 bg-gray-900 text-gray-200 flex flex-col">
            <div class="px-5 py-4 border-b border-gray-700">
                <span class="text-white font-semibold text-base tracking-tight">jr-developer</span>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors"
                    :class="isActive(item.route)
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
                >
                    <component :is="item.icon" class="w-4 h-4 flex-shrink-0" />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="px-3 py-4 border-t border-gray-700">
                <Link
                    :href="route('setup')"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
                >
                    <WrenchScrewdriverIcon class="w-4 h-4" />
                    Setup Wizard
                </Link>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Header -->
            <header class="h-14 flex-shrink-0 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <h1 class="text-base font-medium text-gray-800">{{ title }}</h1>
                <div v-if="$page.props.auth.user" class="text-sm text-gray-500">
                    {{ $page.props.auth.user.name }}
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="$page.props.flash.success || $page.props.flash.error" class="px-6 pt-4">
                <div
                    v-if="$page.props.flash.success"
                    class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>
                <div
                    v-if="$page.props.flash.error"
                    class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"
                >
                    {{ $page.props.flash.error }}
                </div>
            </div>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    Squares2X2Icon,
    FolderIcon,
    Cog6ToothIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();

const navItems = [
    { route: 'dashboard',      label: 'Dashboard', icon: Squares2X2Icon },
    { route: 'projects.index', label: 'Projects',  icon: FolderIcon },
    { route: 'settings',       label: 'Settings',  icon: Cog6ToothIcon },
];

function isActive(routeName) {
    return page.url.startsWith('/' + routeName.replace('.index', '').replace('.', '/'));
}
</script>
