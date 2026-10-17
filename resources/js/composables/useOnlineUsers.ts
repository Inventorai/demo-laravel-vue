import { onMounted, onUnmounted, ref } from 'vue';
import { useEcho } from '@/composables/useEcho';

type Member = { id: string | number; observer?: boolean };

/**
 * The ids of the team members online right now.
 *
 * The API's team presence channel lists everyone signed in to the Inventorai
 * apps. A team token joins it as a hidden observer, flagged observer: true, so
 * it can watch without counting as a person; those entries, this tab's own
 * among them, are dropped. here gives the starting list, and joining and
 * leaving keep it current.
 *
 * Stays empty when Reverb isn't configured or there is no team.
 */
export function useOnlineUsers(teamId: () => string | null | undefined) {
    const online = ref(new Set<string>());
    const echo = useEcho();
    const channel = () => `team.${teamId()}.presence`;

    const update = (change: (ids: Set<string>) => void) => {
        const ids = new Set(online.value);
        change(ids);
        online.value = ids;
    };

    onMounted(() => {
        if (!echo || !teamId()) return;

        echo.join(channel())
            .here((members: Member[]) => {
                online.value = new Set(members.filter((m) => !m.observer).map((m) => String(m.id)));
            })
            .joining((member: Member) => {
                if (!member.observer) update((ids) => ids.add(String(member.id)));
            })
            .leaving((member: Member) => {
                update((ids) => ids.delete(String(member.id)));
            });
    });

    onUnmounted(() => {
        if (teamId()) {
            echo?.leave(channel());
        }
    });

    return online;
}
