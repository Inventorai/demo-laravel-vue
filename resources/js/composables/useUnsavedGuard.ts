import { inject, onMounted, onUnmounted, provide, type ComputedRef, type InjectionKey } from 'vue';
import { router } from '@inertiajs/vue3';

type DirtySource = ComputedRef<number>;

const UnsavedSources: InjectionKey<Set<DirtySource>> = Symbol('inventorai:unsaved-sources');

/**
 * Warns before unsaved edits are thrown away.
 *
 * Nothing on the inspection page saves as you type, and the edits are spread
 * across the area/item tree plus four cards, so the page collects what each
 * part is holding rather than trying to know about them itself: the page calls
 * useUnsavedGuard(), and anything with pending edits registers a count with
 * useUnsavedSource().
 *
 * Two different exits need covering. A real page unload — tab close, reload,
 * a link out of the app — is only visible to `beforeunload`. An Inertia visit
 * never unloads the page at all, so it needs the router's own `before` event.
 */
/**
 * Whether an Inertia visit is actually leaving this page.
 *
 * Most visits are not: every save on this page is a visit, hovering a
 * prefetching link is a visit, and so is the reload the Echo listener fires
 * when someone else uploads a photo. None of those lose anything.
 */
export function leavesPage(
    visit: { method: string; prefetch?: boolean; url: { href: string } },
    currentHref: string,
): boolean {
    if (visit.method !== 'get') {
        return false;
    }
    if (visit.prefetch) {
        return false;
    }

    return visit.url.href !== currentHref;
}

export function useUnsavedGuard(own?: DirtySource): void {
    const sources = new Set<DirtySource>();
    provide(UnsavedSources, sources);

    // The page's own count cannot come back through useUnsavedSource: Vue
    // resolves inject() from the *parent* chain, so a component never sees
    // what it provided itself. It is passed in directly instead.
    if (own) {
        sources.add(own);
    }

    const total = () => [...sources].reduce((sum, source) => sum + source.value, 0);

    const onBeforeUnload = (event: BeforeUnloadEvent) => {
        if (total() === 0) {
            return;
        }
        // preventDefault is what asks for the prompt in current browsers; the
        // legacy path needs a non-empty returnValue. Browsers show their own
        // wording either way and ignore this string.
        event.preventDefault();
        event.returnValue = 'You have unsaved changes.';
    };

    let stopRouterGuard: VoidFunction | null = null;

    onMounted(() => {
        window.addEventListener('beforeunload', onBeforeUnload);

        stopRouterGuard = router.on('before', (event) => {
            if (total() === 0 || !leavesPage(event.detail.visit, window.location.href)) {
                return;
            }

            // Returning false cancels the visit and keeps the user here.
            return window.confirm(
                total() === 1
                    ? 'You have one unsaved change on this page. Leave and lose it?'
                    : `You have ${total()} unsaved changes on this page. Leave and lose them?`,
            );
        });
    });

    onUnmounted(() => {
        window.removeEventListener('beforeunload', onBeforeUnload);
        stopRouterGuard?.();
    });
}

/**
 * Registers one part of the page's unsaved count with the guard above.
 *
 * Does nothing when no guard is present, so a card stays usable on its own.
 */
export function useUnsavedSource(source: DirtySource): void {
    const sources = inject(UnsavedSources, null);
    if (!sources) {
        return;
    }

    sources.add(source);
    onUnmounted(() => sources.delete(source));
}
