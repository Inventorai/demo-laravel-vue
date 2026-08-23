import { computed, ref, watch } from 'vue';

/**
 * Editable copies of a list of API records.
 *
 * Every record type on the inspection page is edited in place and saved on
 * demand, one record at a time. Drafts are rebuilt from the payload whenever
 * the server sends new records, so a save always leaves the row showing what
 * the API actually stored; alongside them sits a snapshot of that server
 * state, which is what lets a row report unsaved edits.
 *
 * @param source  the records as they arrive from the API
 * @param build   the editable shape for one record
 */
export function useDrafts(
    source: () => Record<string, any>[] | undefined,
    build: (record: Record<string, any>) => Record<string, any>,
) {
    const drafts = ref<Record<string, any>>({});
    const saved = ref<Record<string, string>>({});

    watch(source, (list) => {
        const next: Record<string, any> = {};
        (list ?? []).forEach((record) => { next[record.id] = build(record); });
        drafts.value = next;
        saved.value = Object.fromEntries(
            Object.entries(next).map(([id, draft]) => [id, JSON.stringify(draft)]),
        );
    }, { immediate: true });

    const isDirty = (id: string) => id in drafts.value && JSON.stringify(drafts.value[id]) !== saved.value[id];

    const dirtyCount = computed(() => Object.keys(drafts.value).filter(isDirty).length);

    return { drafts, isDirty, dirtyCount };
}
