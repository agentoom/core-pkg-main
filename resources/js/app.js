import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { Passkeys } from '@laravel/passkeys';
window.Passkeys = Passkeys;

// Inertia pages and route() paths contributed by sibling packages. A package
// page is rendered with a "package::Path/To/Page" name from its controller.
// Both glob patterns must stay string literals for Vite to resolve them.
const packageRouteModules = import.meta.glob('../../../*/resources/js/routes.js', { eager: true });

const packageRoutes = Object.values(packageRouteModules)
    .reduce((routes, module) => Object.assign(routes, module.default ?? {}), {});

// Shim for route() function as Ziggy is not installed
window.route = (name, params) => {
    const routes = {
        'admin.pipelines.update': '/admin/pipelines/:pipeline',
        'admin.pipelines.store': '/admin/pipelines',
        'admin.pipelines.clone': '/admin/pipelines/:pipeline/clone',
        'admin.pipelines.index': '/admin/pipelines',
        'admin.pipelines.edit': '/admin/pipelines/:pipeline/edit',
        'admin.pipelines.create': '/admin/pipelines/create',
        'admin.pipelines.legacy.edit': '/admin/pipelines/legacy/:pipeline/edit',
        'admin.pipelines.legacy.create': '/admin/pipelines/legacy/create',
        'admin.v2.chat.index': '/admin/v2/chat',
        'admin.chat.embed': '/admin/chat/embed',
        'admin.v2.chat.send': '/admin/v2/chat/send',
        'admin.v2.chat.ask': '/admin/v2/chat/ask/:conversationId',
        'admin.v2.chat.destroy': '/admin/v2/chat/:id',
        'admin.v2.chat.stop': '/admin/v2/chat/stop/:conversationId',
        'admin.v2.chat.share': '/admin/v2/chat/:conversationId/share',
        'admin.v2.chat.unshare': '/admin/v2/chat/:conversationId/share',
        ...packageRoutes,
    };

    let url = routes[name];
    if (!url) {
        console.warn(`Route [${name}] not found in shim.`);
        return '';
    }

    if (params) {
        if (typeof params === 'object') {
            Object.keys(params).forEach(key => {
                url = url.replace(`:${key}`, params[key]);
            });
        } else {
            url = url.replace(/:[a-zA-Z0-9_]+/, params);
        }
    }

    return url;
};

export function setupAgentoomApp(el) {
    if (!el || el.__vue_app__ || el.__agentoom_init__) {
        return;
    }
    el.__agentoom_init__ = true;

    createInertiaApp({
        title: (title) => `${title} - Agentoom`,
        resolve: (name) => {
            const pages = import.meta.glob([
                './Pages/**/*.vue',
                '../../../*/resources/js/Pages/**/*.vue',
            ]);

            // "package::Admin/V2/Page" resolves into that package's pages.
            if (name.includes('::')) {
                const [pkg, page] = name.split('::');

                return resolvePageComponent(`../../../${pkg}/resources/js/Pages/${page}.vue`, pages);
            }

            return resolvePageComponent(`./Pages/${name}.vue`, pages);
        },
        setup({ el, App, props, plugin }) {
            const app = createApp({ render: () => h(App, props) })
                .use(plugin);
            app.config.globalProperties.route = window.route;
            app.mount(el);
        },
    });
}
