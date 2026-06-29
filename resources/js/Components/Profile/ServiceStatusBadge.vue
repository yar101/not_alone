<script setup>
import { computed } from "vue";
import { useTranslations } from "@/composables/useTranslations";

const { __ } = useTranslations();

const props = defineProps({
    status: { type: String, required: true },
    isChangeRequest: { type: Boolean, default: false },
});

const LABELS = computed(() => {
    if (props.isChangeRequest) {
        return {
            pending: __("profile.services.status.change_pending"),
            approved: __("profile.services.status.approved", "Одобрено"),
            has_remarks: __("profile.services.status.remarks"),
            rejected: __("profile.services.status.rejected"),
        };
    }
    return {
        pending: __("profile.services.status.pending"),
        approved: __("profile.services.status.approved", "Одобрено"),
        has_remarks: __("profile.services.status.remarks"),
        rejected: __("profile.services.status.rejected"),
        hidden: __("profile.services.status.hidden"),
        trial: "1-й заказ 0 ₽",
    };
});

const STYLES = {
    pending: {
        color: "var(--color-base-1)",
        bg: "rgba(255, 178, 239,0.14)",
        border: "rgba(255, 178, 239,0.28)",
        shine: "rgba(200,200,255,0.65)",
    },
    approved: {
        color: "#70e0a8",
        bg: "rgba(80,210,140,0.14)",
        border: "rgba(80,210,140,0.28)",
        shine: "rgba(120,240,175,0.60)",
    },
    has_remarks: {
        color: "#ff8f8f",
        bg: "rgba(255,100,100,0.14)",
        border: "rgba(255,100,100,0.28)",
        shine: "rgba(255,150,150,0.62)",
    },
    rejected: {
        color: "#ff6666",
        bg: "rgba(220,60,60,0.14)",
        border: "rgba(220,60,60,0.28)",
        shine: "rgba(255,120,120,0.58)",
    },
    hidden: {
        color: "rgba(180,180,200,0.65)",
        bg: "rgba(180,180,200,0.08)",
        border: "rgba(180,180,200,0.18)",
        shine: "rgba(220,220,230,0.38)",
    },
    trial: {
        color: "#5bc0de",
        bg: "rgba(91,192,222,0.14)",
        border: "rgba(91,192,222,0.28)",
        shine: "rgba(150,220,250,0.60)",
    },
};

const label = computed(() => LABELS.value[props.status] ?? props.status);
const style = computed(() => STYLES[props.status] ?? STYLES.pending);
</script>

<template>
    <span
        class="ssb"
        :style="{
            '--ssb-color': style.color,
            '--ssb-bg': style.bg,
            '--ssb-border': style.border,
            '--ssb-shine': style.shine,
        }"
        >{{ label }}</span
    >
</template>

<style scoped>
.ssb {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    padding: 0.2rem 0.65rem;
    margin: 0 0.4rem;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    white-space: nowrap;
    color: var(--ssb-color);
    background: var(--ssb-bg);
    border: 1px solid var(--ssb-border);
    border-top: none;
    box-shadow:
        inset 0 1px 0 var(--ssb-shine),
        inset 0 -1px 0 rgba(0, 0, 0, 0.12),
        0 1px 3px rgba(0, 0, 0, 0.2);
}
</style>
