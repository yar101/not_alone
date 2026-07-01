<script setup>
import {
    ref,
    onMounted,
    computed,
    inject,
    provide,
    reactive,
    watch,
} from "vue";
import { Head, Link, useForm, usePage, router } from "@inertiajs/vue3";
import { useTranslations } from "@/composables/useTranslations.js";
import SiteModal from "@/Components/Site/SiteModal.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import {
    StarFilled,
    MagicStick,
    User,
    Memo,
    Briefcase,
    Film,
} from "@element-plus/icons-vue";

defineOptions({ layout: AppLayout });
import ProfileHeader from "@/Components/Profile/ProfileHeader.vue";
import ProfileChecklist from "@/Components/Profile/ProfileChecklist.vue";
import ProfileAbout from "@/Components/Profile/ProfileAbout.vue";
import ProfileTraits from "@/Components/Profile/ProfileTraits.vue";
import ProfileInterests from "@/Components/Profile/ProfileInterests.vue";
import ProfileLanguages from "@/Components/Profile/ProfileLanguages.vue";
import ProfilePosts from "@/Components/Profile/ProfilePosts.vue";
import ProfileReviews from "@/Components/Profile/ProfileReviews.vue";
import ProfileServices from "@/Components/Profile/ProfileServices.vue";
import ProfileVoice from "@/Components/Profile/ProfileVoice.vue";
import ProfileContent from "@/Components/Profile/ProfileContent.vue";

const props = defineProps({
    profileUser: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
    isIdol: { type: Boolean, default: false },
    rating: { default: null },
    // Deferred props — no type constraint; Inertia passes null until resolved
    traits: { default: null },
    interests: { default: null },
    languages: { default: null },
    allTraits: { default: null },
    allCategories: { default: null },
    services: { default: null },
    serviceCategories: { default: null },
    serviceTimeUnits: { default: null },
    isBlockedByIdol: { type: Boolean, default: false },
    isFollowing: { type: Boolean, default: false },
    contentPacks: { default: null },
    purchasedPackIds: { default: () => [] },
});

// ── Auth ──────────────────────────────────────────────────────
const openAuth = inject("openAuth", null);

// ── Chat ──────────────────────────────────────────────────────
const openChatWith = inject("openChatWith", null);
function openChat() {
    if (!page.props.auth?.user) {
        openAuth?.("register");
        return;
    }
    openChatWith?.(props.profileUser.id);
}

// ── Follow ──────────────────────────────────────────────────────
const showUnfollowConfirm = ref(false);
function toggleFollow() {
    if (!page.props.auth?.user) {
        openAuth?.("register");
        return;
    }

    if (props.isFollowing) {
        showUnfollowConfirm.value = true;
    } else {
        performFollowRequest();
    }
}

function confirmUnfollow() {
    showUnfollowConfirm.value = false;
    performFollowRequest();
}

function performFollowRequest() {
    router.post(
        route("users.follow", props.profileUser.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

const { __ } = useTranslations();

// ── Email verification banner ─────────────────────────────────
const page = usePage();
const showVerificationBanner = computed(
    () => props.isOwner && !page.props.auth?.user?.email_verified_at,
);
const verificationForm = useForm({});
const resendSent = ref(false);
function resendVerification() {
    verificationForm.post(route("verification.send"), {
        onSuccess: () => {
            resendSent.value = true;
        },
    });
}

// ── Tabs ─────────────────────────────────────────────────────
const TAB_ORDER = props.isIdol
    ? ["about", "posts", "services", "content", "reviews"]
    : ["about", "posts", "services", "content"];
const storedTab = sessionStorage.getItem(`profile_tab_${props.profileUser.id}`);
const hashTab = window.location.hash.slice(1);
const initialTab = TAB_ORDER.includes(hashTab)
    ? hashTab
    : TAB_ORDER.includes(storedTab)
      ? storedTab
      : "about";
const tab = ref(initialTab);

const serviceNav = reactive({
    inCategory: false,
    accent: "#ffb2ef",
    onBack: null,
});
provide("serviceNav", serviceNav);

function switchTab(name) {
    tab.value = name;
    history.replaceState(null, "", "#" + name);
    sessionStorage.setItem(`profile_tab_${props.profileUser.id}`, name);
}

// ── Report modal ──────────────────────────────────────────
const showReportModal = ref(false);
const reportForm = ref({ reason: "", details: "" });
const reportErrors = ref({});
const reportSent = ref(false);

const reportReasons = computed(() => [
    { value: "spam", label: __("profile.report.reasons.spam") },
    { value: "inappropriate", label: __("profile.report.reasons.inappropriate") },
    { value: "fraud", label: __("profile.report.reasons.fraud") },
    { value: "harassment", label: __("profile.report.reasons.harassment") },
    { value: "other", label: __("profile.report.reasons.other") },
]);

function openReportModal() {
    reportForm.value = { reason: "", details: "" };
    reportErrors.value = {};
    reportSent.value = false;
    showReportModal.value = true;
}

function submitReport() {
    reportErrors.value = {};
    router.post(
        route("reports.store"),
        {
            reported_id: props.profileUser.id,
            reason: reportForm.value.reason,
            details: reportForm.value.details,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                reportSent.value = true;
            },
            onError: (errors) => {
                reportErrors.value = errors;
            },
        },
    );
}

// ── driver.js Tour ─────────────────────────────────────────
const TOUR_KEY = "profile_tour_done";
let activeDriverObj = null;

const tourIsActive = ref(false);

onMounted(async () => {
    if (!props.isOwner) return;
    if (localStorage.getItem(TOUR_KEY)) return;

    const { driver } = await import("driver.js");
    await import("driver.js/dist/driver.css");

    tourIsActive.value = true;

    activeDriverObj = driver({
        showProgress: true,
        allowClose: true,
        disableActiveInteraction: true,
        overlayClickBehavior: () => {
            // Do nothing on overlay click, as requested by user
        },
        progressText: __("profile.tour.progressText"),
        nextBtnText: __("profile.tour.next"),
        prevBtnText: __("profile.tour.prev"),
        doneBtnText: __("profile.tour.done"),
        closeBtnText: "✕",
        showButtons: ["next", "close"],
        onPopoverRender: (popover) => {
            if (popover.closeButton) {
                popover.closeButton.innerText = "✕";
            }
        },
        onHighlightStarted: (element, step, { state }) => {
        },
        steps: [
            {
                element: "#tour-header",
                popover: {
                    title: __("profile.tour.header.title"),
                    description: __("profile.tour.header.desc"),
                    side: "bottom",
                },
            },
            {
                element: ".profile-tabs",
                popover: {
                    title: __("profile.tour.tabs.title"),
                    description: __("profile.tour.tabs.desc"),
                    side: "bottom",
                },
            },
            {
                element: "#tour-about",
                popover: {
                    title: __("profile.tour.about.title"),
                    description: __("profile.tour.about.desc"),
                    side: "bottom",
                },
            },
            {
                element: "#tour-voice",
                popover: {
                    title: __("profile.tour.voice.title"),
                    description: __("profile.tour.voice.desc"),
                    side: "bottom",
                },
            },
            {
                element: "#tour-traits",
                popover: {
                    title: __("profile.tour.traits.title"),
                    description: __("profile.tour.traits.desc"),
                    side: "top",
                },
            },
            {
                element: "#tour-interests",
                popover: {
                    title: __("profile.tour.interests.title"),
                    description: __("profile.tour.interests.desc"),
                    side: "bottom",
                },
            },
            {
                element: ".pcl",
                popover: {
                    title: __("profile.tour.checklist.title"),
                    description: __("profile.tour.checklist.desc"),
                    side: "bottom",
                    align: "start",
                },
            },
            {
                element: "#tour-cart",
                popover: {
                    title: __("profile.tour.cart.title"),
                    description: __("profile.tour.cart.desc"),
                    side: "bottom",
                },
            },
            {
                element: "#tour-notifications",
                popover: {
                    title: __("profile.tour.notifications.title"),
                    description: __("profile.tour.notifications.desc"),
                    side: "bottom",
                },
            },
            {
                element: "#tour-chat",
                popover: {
                    title: __("profile.tour.chat.title"),
                    description: __("profile.tour.chat.desc"),
                    side: "bottom",
                },
                onHighlightStarted: () => {
                    window.dispatchEvent(new CustomEvent("noalone:toggle-chat", { detail: false }));
                },
            },
            {
                element: ".chat-sidebar",
                popover: {
                    title: __("profile.tour.chat_sidebar.title"),
                    description: __("profile.tour.chat_sidebar.desc"),
                    side: "left",
                },
                onHighlightStarted: () => {
                    window.dispatchEvent(new CustomEvent("noalone:toggle-chat", { detail: true }));
                },
            },
            {
                element: ".chat-tabs",
                popover: {
                    title: __("profile.tour.chat_tabs.title"),
                    description: __("profile.tour.chat_tabs.desc"),
                    side: "left",
                },
            },
            {
                element: ".chat-sidebar__search",
                popover: {
                    title: __("profile.tour.chat_search.title"),
                    description: __("profile.tour.chat_search.desc"),
                    side: "left",
                },
            },
            {
                element: "#tour-user-chip",
                popover: {
                    title: __("profile.tour.user_chip.title"),
                    description: __("profile.tour.user_chip.desc"),
                    side: "bottom",
                },
                onHighlightStarted: () => {
                    window.dispatchEvent(new CustomEvent("noalone:toggle-chat", { detail: false }));
                    window.dispatchEvent(new CustomEvent("noalone:toggle-sidebar", { detail: false }));
                },
            },
            {
                element: ".usb-hero",
                popover: {
                    title: __("profile.tour.usb_hero.title"),
                    description: __("profile.tour.usb_hero.desc"),
                    side: "left",
                },
                onHighlightStarted: () => {
                    window.dispatchEvent(new CustomEvent("noalone:toggle-sidebar", { detail: true }));
                },
            },
            {
                element: ".usb-nav",
                popover: {
                    title: __("profile.tour.usb_nav.title"),
                    description: __("profile.tour.usb_nav.desc"),
                    side: "left",
                },
            },
            {
                element: ".usb-features",
                popover: {
                    title: __("profile.tour.usb_features.title"),
                    description: __("profile.tour.usb_features.desc"),
                    side: "left",
                },
                onHighlightStarted: () => {
                    window.dispatchEvent(new CustomEvent("noalone:toggle-sidebar", { detail: true }));
                },
            },
        ],
        onDestroyed: () => {
            tourIsActive.value = false;
            localStorage.setItem(TOUR_KEY, "1");
            window.dispatchEvent(new CustomEvent("noalone:toggle-chat", { detail: false }));
            window.dispatchEvent(new CustomEvent("noalone:toggle-sidebar", { detail: false }));
        },
    });

    
    activeDriverObj.drive();
});
</script>

<template>
    <Head :title="profileUser.name + ' — профиль'" />

    <div class="profile-page">
        <div class="profile-container">
            <!-- Email verification banner -->
            <div v-if="showVerificationBanner" class="verify-banner">
                <span class="verify-banner__icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                        />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                </span>
                <span class="verify-banner__text">
                    Подтвердите email — мы отправили письмо на
                    <strong>{{ page.props.auth.user.email }}</strong>
                </span>
                <span v-if="resendSent" class="verify-banner__sent"
                    >Письмо отправлено</span
                >
                <button
                    v-else
                    class="verify-banner__btn"
                    :disabled="verificationForm.processing"
                    @click="resendVerification"
                >
                    Отправить повторно
                </button>
            </div>

            <!-- Two-column body -->
            <div class="profile-body">
                <!-- Left sidebar: header + vertical tabs -->
                <div class="profile-sidebar">
                    <ProfileHeader
                        class="page-block"
                        :user="profileUser"
                        :is-owner="isOwner"
                        :is-idol="isIdol"
                        :is-following="isFollowing"
                        :rating="rating"
                        :can-report="!isOwner && !!page.props.auth?.user"
                        @report="openReportModal"
                    />
                    <ProfileChecklist
                        v-if="isOwner"
                        :user="profileUser"
                        :traits="traits"
                        :interests="interests"
                        :languages="languages"
                    />
                    <div
                        v-if="!isOwner && page.props.auth?.user"
                        class="sidebar-actions"
                    >
                        <button
                            v-if="isIdol"
                            class="sidebar-subscribe-btn"
                            :class="{
                                'sidebar-subscribe-btn--active': isFollowing,
                            }"
                            @click="toggleFollow"
                        >
                            <span>{{
                                isFollowing
                                    ? __("profile.unfollow")
                                    : __("profile.follow")
                            }}</span>
                        </button>

                        <SiteModal
                            :show="showUnfollowConfirm"
                            @close="showUnfollowConfirm = false"
                            compact
                            max-width="500px"
                            variant="pink"
                            :no-padding="true"
                        >
                            <div class="unfollow-confirm">
                                <div class="unfollow-confirm__body">
                                    <div class="unfollow-confirm__icon">
                                        <svg
                                            width="24"
                                            height="24"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path
                                                d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="unfollow-confirm__content">
                                        <div class="unfollow-confirm__title">
                                            {{
                                                __(
                                                    "profile.unfollow_confirm_title",
                                                )
                                            }}
                                        </div>
                                        <div class="unfollow-confirm__text">
                                            {{
                                                __(
                                                    "profile.unfollow_confirm_body",
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="unfollow-confirm__actions">
                                    <button
                                        class="unfollow-confirm__btn unfollow-confirm__btn--cancel"
                                        @click="showUnfollowConfirm = false"
                                    >
                                        {{ __("common.no") }}
                                    </button>
                                    <button
                                        class="unfollow-confirm__btn unfollow-confirm__btn--confirm"
                                        @click="confirmUnfollow"
                                    >
                                        {{ __("common.yes") }}
                                    </button>
                                </div>
                            </div>
                        </SiteModal>

                        <button
                            v-if="page.props.is_idol && !isIdol"
                            class="sidebar-message-btn"
                            @click="openChat"
                            :title="__('profile.message.send')"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                />
                            </svg>
                            <span>{{ __("profile.message.send") }}</span>
                        </button>
                    </div>
                </div>

                <!-- Right main: tabs + scrollable tab content -->
                <div
                    class="profile-main"
                    :class="{
                        'profile-main--banned':
                            profileUser.is_banned && !isOwner,
                    }"
                >
                    <div class="profile-tabs page-block">
                        <button
                            class="tab-btn"
                            :class="{ active: tab === 'about' }"
                            @click="switchTab('about')"
                        >
                            <el-icon class="tab-icon"><User /></el-icon>
                            <span class="tab-label">{{
                                __("profile.tabs.about")
                            }}</span>
                        </button>
                        <button
                            v-if="isIdol"
                            class="tab-btn"
                            :class="{ active: tab === 'posts' }"
                            @click="switchTab('posts')"
                        >
                            <el-icon class="tab-icon"><Memo /></el-icon>
                            <span class="tab-label">{{
                                __("profile.tabs.posts")
                            }}</span>
                        </button>
                        <button
                            class="tab-btn"
                            :class="{ active: tab === 'services' }"
                            @click="switchTab('services')"
                        >
                            <el-icon class="tab-icon"><Briefcase /></el-icon>
                            <span class="tab-label">{{
                                __("profile.tabs.services")
                            }}</span>
                        </button>
                        <button
                            class="tab-btn"
                            :class="{ active: tab === 'content' }"
                            @click="switchTab('content')"
                        >
                            <el-icon class="tab-icon"><Film /></el-icon>
                            <span class="tab-label">{{
                                __("profile.tabs.content")
                            }}</span>
                        </button>
                        <button
                            v-if="isIdol"
                            class="tab-btn"
                            :class="{ active: tab === 'reviews' }"
                            @click="switchTab('reviews')"
                        >
                            <el-icon class="tab-icon"><StarFilled /></el-icon>
                            <span class="tab-label">{{
                                __("profile.tabs.reviews")
                            }}</span>
                        </button>
                        <button
                            v-if="serviceNav.inCategory"
                            class="cd-back"
                            :style="{ '--cat-accent': serviceNav.accent }"
                            @click="serviceNav.onBack?.()"
                        >
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                            {{ __("profile.tabs.back_to_categories") }}
                        </button>
                    </div>

                    <button
                        v-if="serviceNav.inCategory"
                        class="cd-back-mobile"
                        :style="{ '--cat-accent': serviceNav.accent }"
                        @click="serviceNav.onBack?.()"
                    >
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                        {{ __("profile.tabs.back_to_categories") }}
                    </button>

                    <div class="tab-content-wrap page-block">
                        <Transition name="tab-fade" mode="out-in">
                            <div
                                v-if="tab === 'about'"
                                key="about"
                                class="tab-panel"
                            >
                                <!-- Верх: bio слева, диск+плеер справа (eager — не defer) -->
                                <div class="about-top-grid anim-block">
                                    <ProfileAbout
                                        :about="profileUser.about"
                                        :is-owner="isOwner"
                                    />
                                    <div
                                        id="tour-voice"
                                        class="about-voice-col"
                                    >
                                        <ProfileVoice
                                            :voice-url="profileUser.voice_url"
                                            :is-owner="isOwner"
                                        />
                                    </div>
                                </div>

                                <!-- Слитая панель: характер + интересы + языки (deferred) -->
                                <div
                                    v-if="Array.isArray(traits)"
                                    class="fused-panel"
                                >
                                    <div id="tour-traits" class="anim-block">
                                        <ProfileTraits
                                            :traits="traits"
                                            :all-traits="allTraits"
                                            :is-owner="isOwner"
                                            :gender="profileUser.gender"
                                        />
                                    </div>

                                    <div id="tour-interests" class="anim-block">
                                        <ProfileInterests
                                            :interests="interests"
                                            :all-categories="allCategories"
                                            :is-owner="isOwner"
                                        />
                                    </div>

                                    <div class="anim-block">
                                        <ProfileLanguages
                                            :languages="languages"
                                            :is-owner="isOwner"
                                        />
                                    </div>
                                </div>
                                <div v-else class="about-skeleton fused-panel">
                                    <div class="skeleton-row" />
                                    <div
                                        class="skeleton-row skeleton-row--mid"
                                    />
                                    <div
                                        class="skeleton-row skeleton-row--short"
                                    />
                                </div>
                            </div>

                            <div
                                v-else-if="tab === 'posts'"
                                key="posts"
                                class="tab-panel"
                            >
                                <div class="anim-block">
                                    <ProfilePosts
                                        :profile-user-id="profileUser.id"
                                        :is-owner="isOwner"
                                        :is-idol="isIdol"
                                        :auth-user="page.props.auth.user"
                                    />
                                </div>
                            </div>

                            <div
                                v-else-if="tab === 'services'"
                                key="services"
                                class="tab-panel"
                            >
                                <!-- Idol (or idol-owner): show services component -->
                                <template v-if="isIdol">
                                    <div class="anim-block">
                                        <ProfileServices
                                            :services="services"
                                            :service-categories="
                                                serviceCategories
                                            "
                                            :service-time-units="
                                                serviceTimeUnits
                                            "
                                            :is-owner="isOwner"
                                            :is-idol="isIdol"
                                            :profile-user="profileUser"
                                            :is-blocked-by-idol="
                                                isBlockedByIdol
                                            "
                                        />
                                    </div>
                                </template>
                                <!-- Owner but not idol yet -->
                                <template v-else-if="isOwner">
                                    <div class="anim-block idol-cta-block">
                                        <div class="idol-cta-content">
                                            <div class="idol-cta-left">
                                                <span
                                                    class="idol-cta-eyebrow"
                                                    >{{
                                                        __(
                                                            "profile.services.become.eyebrow",
                                                        )
                                                    }}</span
                                                >
                                                <p
                                                    class="idol-cta-title"
                                                    v-html="
                                                        __(
                                                            'profile.services.become.title',
                                                        )
                                                    "
                                                ></p>
                                                <div class="idol-cta-tags">
                                                    <span
                                                        class="idol-cta-tag"
                                                        >{{
                                                            __(
                                                                "profile.services.become.tag1",
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        class="idol-cta-tag"
                                                        >{{
                                                            __(
                                                                "profile.services.become.tag2",
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <Link
                                                href="/idol/apply"
                                                class="idol-cta-btn"
                                                >{{
                                                    __(
                                                        "profile.services.become.apply",
                                                    )
                                                }}</Link
                                            >
                                        </div>
                                    </div>
                                </template>
                                <!-- Visitor viewing a non-idol profile -->
                                <template v-else>
                                    <div class="anim-block coming-soon-block">
                                        <p class="coming-soon-title">Услуги</p>
                                        <p class="coming-soon-text">
                                            У этого пользователя нет услуг
                                        </p>
                                    </div>
                                </template>
                            </div>

                            <div
                                v-else-if="tab === 'reviews'"
                                key="reviews"
                                class="tab-panel"
                            >
                                <ProfileReviews
                                    :profile-user-id="profileUser.id"
                                    :is-owner="isOwner"
                                    :is-idol="isIdol"
                                />
                            </div>

                            <div v-else key="content" class="tab-panel">
                                <ProfileContent
                                    :content-packs="contentPacks"
                                    :purchased-pack-ids="purchasedPackIds"
                                    :is-owner="isOwner"
                                    :is-idol="isIdol"
                                    :profile-user="profileUser"
                                />
                            </div>
                        </Transition>
                    </div>
                    <!-- /tab-content-wrap -->

                    <div
                        v-if="profileUser.is_banned && !isOwner"
                        class="profile-banned-overlay"
                    >
                        <div class="profile-banned-card">
                            <div class="profile-banned-card__stripe" />
                            <div class="profile-banned-card__body">
                                <span class="profile-banned-card__label"
                                    >СТАТУС АККАУНТА</span
                                >
                                <div class="profile-banned-card__header">
                                    <svg
                                        class="profile-banned-card__icon"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <circle cx="12" cy="12" r="10" />
                                        <line
                                            x1="4.93"
                                            y1="4.93"
                                            x2="19.07"
                                            y2="19.07"
                                        />
                                    </svg>
                                    <p class="profile-banned-card__title">
                                        Аккаунт заблокирован
                                    </p>
                                </div>
                                <p class="profile-banned-card__sub">
                                    Пользователь заблокирован администрацией
                                    платформы
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /profile-main -->
            </div>
            <!-- /profile-body -->
        </div>
    </div>

    <!-- Report modal -->
    <SiteModal
        :show="showReportModal"
        variant="pink"
        :compact="true"
        @close="showReportModal = false"
    >
        <div v-if="reportSent" class="report-success">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                width="36"
                height="36"
            >
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
            <p>{{ __("profile.report.success") }}</p>
            <button class="report-btn-close" @click="showReportModal = false">
                {{ __("profile.report.close") }}
            </button>
        </div>

        <form v-else @submit.prevent="submitReport" class="report-form">
            <h3 class="report-title">
                {{ __("profile.report.title", { name: profileUser.name }) }}
            </h3>

            <div class="report-field">
                <label class="report-label">{{ __("profile.report.reason_label") }}</label>
                <div class="report-reasons">
                    <button
                        v-for="r in reportReasons"
                        :key="r.value"
                        type="button"
                        class="report-reason-btn"
                        :class="{
                            'report-reason-btn--active':
                                reportForm.reason === r.value,
                        }"
                        @click="reportForm.reason = r.value"
                    >
                        {{ r.label }}
                    </button>
                </div>
                <p v-if="reportErrors.reason" class="report-err">
                    {{ reportErrors.reason }}
                </p>
            </div>

            <div class="report-field">
                <label class="report-label"
                    >{{ __("profile.report.details_label") }}
                    <span class="report-optional"
                        >({{ __("profile.report.min_symbols") }})</span
                    ></label
                >
                <textarea
                    v-model="reportForm.details"
                    class="report-textarea"
                    rows="4"
                    maxlength="1000"
                    :placeholder="__('profile.report.details_placeholder')"
                    required
                    minlength="10"
                    :class="{ 'report-textarea--err': reportErrors.details }"
                />
                <p v-if="reportErrors.details" class="report-err">
                    {{ reportErrors.details }}
                </p>
            </div>

            <div class="report-actions">
                <button
                    type="button"
                    class="report-btn-cancel"
                    @click="showReportModal = false"
                >
                    {{ __("profile.report.cancel") }}
                </button>
                <button
                    type="submit"
                    class="report-btn-submit"
                    :disabled="
                        !reportForm.reason ||
                        reportForm.details.trim().length < 10
                    "
                >
                    {{ __("profile.report.submit") }}
                </button>
            </div>
        </form>
    </SiteModal>
</template>

<!-- driver.js dark theme override (non-scoped) -->
<style>
.driver-overlay {
    opacity: 0.97 !important; 
}
.driver-popover {
    background: #0c0c14 !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8),
                0 0 16px color-mix(in srgb, var(--color-base-1), transparent 92%),
                0 0 24px color-mix(in srgb, var(--color-base-2), transparent 94%) !important;
    border-radius: 8px !important;
    font-family: "Rubik", sans-serif !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    max-width: 350px !important; /* Made wider for better text flow */
}
.driver-popover-title {
    color: #ffffff !important;
    font-size: 1.15rem !important;
    padding-right: 45px !important; /* Space for the square close button */
}
.driver-popover-description {
    color: rgba(255, 255, 255, 0.6) !important;
    font-size: 1rem !important;
    line-height: 1.6 !important;
}
.driver-popover-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.07) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 20px !important;
    padding-top: 12px !important;
    margin-top: 12px !important;
}
.driver-popover-prev-btn {
    background: color-mix(in srgb, var(--color-base-1), transparent 90%) !important;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 60%) !important;
    color: var(--color-base-1) !important;
    border-radius: 4px !important;
    text-shadow: none !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
    padding: 10px 20px !important;
    font-size: 1rem !important;
}
.driver-popover-prev-btn:hover {
    background: color-mix(in srgb, var(--color-base-1), transparent 75%) !important;
    border-color: var(--color-base-1) !important;
    color: #fff !important;
    box-shadow: 0 0 4px color-mix(in srgb, var(--color-base-1), transparent 80%) !important;
}
.driver-popover-next-btn,
.driver-popover-done-btn {
    background: color-mix(in srgb, var(--color-base-2), transparent 90%) !important;
    border: 1px solid color-mix(in srgb, var(--color-base-2), transparent 60%) !important;
    color: var(--color-base-2) !important;
    border-radius: 4px !important;
    text-shadow: none !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
    padding: 10px 20px !important;
    font-size: 1rem !important;
}
.driver-popover-next-btn:hover,
.driver-popover-done-btn:hover {
    background: color-mix(in srgb, var(--color-base-2), transparent 75%) !important;
    border-color: var(--color-base-2) !important;
    color: #fff !important;
    box-shadow: 0 0 4px color-mix(in srgb, var(--color-base-2), transparent 80%) !important;
}
.driver-popover-navigation-btns {
    margin-top: 10px !important;
}
.driver-popover-progress-text {
    color: rgba(255, 255, 255, 0.3) !important;
    font-size: 0.95rem !important;
}
.driver-popover-arrow {
    display: none !important;
}

/* Pink close button styling (no background/border) */
.driver-popover-close-btn {
    all: unset;
    cursor: pointer;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 28px !important;
    height: 28px !important;
    background: transparent !important;
    border: none !important;
    color: #ff4b4b !important;
    border-radius: 50% !important;
    font-size: 1.2rem !important;
    font-weight: bold !important;
    top: 12px !important;
    right: 12px !important;
    position: absolute !important;
    pointer-events: auto !important;
    transition: all 0.2s ease !important;
    box-shadow: none !important;
}
.driver-popover-close-btn:hover {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.08) !important;
}

/* Desktop size reductions for clean visual layout */
@media (min-width: 769px) {
    .driver-popover-title {
        font-size: 0.95rem !important;
    }
    .driver-popover-description {
        font-size: 0.82rem !important;
        line-height: 1.5 !important;
    }
    .driver-popover-prev-btn,
    .driver-popover-next-btn,
    .driver-popover-done-btn {
        padding: 6px 14px !important;
        font-size: 0.8rem !important;
    }
    .driver-popover-progress-text {
        font-size: 0.8rem !important;
    }
    .driver-popover-close-btn {
        font-size: 0.95rem !important;
        width: 22px !important;
        height: 22px !important;
        top: 10px !important;
        right: 10px !important;
    }
}

/* Forbid scrolling when driver is active */
body.driver-active {
    overflow: hidden !important;
}
</style>

<style scoped>


/* ── Verification banner ──────────────────────────────────── */
.verify-banner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(234, 179, 8, 0.3);
    border-radius: 3px;
    background: rgba(234, 179, 8, 0.06);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.verify-banner__icon {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    color: rgba(234, 179, 8, 0.8);
}
.verify-banner__icon svg {
    width: 1rem;
    height: 1rem;
}

.verify-banner__text {
    flex: 1;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    min-width: 0;
}
.verify-banner__text strong {
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
}

.verify-banner__sent {
    font-size: 0.82rem;
    color: rgba(74, 222, 128, 0.8);
    white-space: nowrap;
}

.verify-banner__btn {
    flex-shrink: 0;
    padding: 0.3rem 0.75rem;
    border: 1px solid rgba(234, 179, 8, 0.35);
    border-radius: 3px;
    background: transparent;
    color: rgba(234, 179, 8, 0.85);
    font-size: 0.8rem;
    font-family: inherit;
    cursor: pointer;
    transition:
        border-color 0.15s,
        color 0.15s;
    white-space: nowrap;
}
.verify-banner__btn:hover:not(:disabled) {
    border-color: rgba(234, 179, 8, 0.7);
    color: rgba(234, 179, 8, 1);
}
.verify-banner__btn:disabled {
    opacity: 0.4;
    cursor: default;
}

/* ── Страница ─────────────────────────────────────────────── */
.profile-page {
    height: calc(100vh - 60px);
    overflow: hidden;
    padding: 0 1.5rem;
    box-sizing: border-box;
    font-family: "Rubik", sans-serif;
}

.profile-container {
    max-width: 1440px;
    margin: 0 auto;
    padding-top: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* ── Entrance animation ───────────────────────────────────── */
@keyframes pb-in {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.page-block {
    animation: pb-in 0.4s cubic-bezier(0.33, 1, 0.68, 1) both;
}
.page-block:nth-child(1) {
    animation-delay: 0s;
}
.page-block:nth-child(2) {
    animation-delay: 0.08s;
}
.page-block:nth-child(3) {
    animation-delay: 0.16s;
}
.page-block:nth-child(4) {
    animation-delay: 0.24s;
}

/* ── Two-column body ──────────────────────────────────────── */
.profile-body {
    display: flex;
    flex: 1;
    min-height: 0;
    gap: 1rem;
}

.profile-sidebar {
    width: 380px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 1rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(190, 145, 255, 0.25) transparent;
}
.profile-sidebar::-webkit-scrollbar {
    width: 3px;
}
.profile-sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.profile-sidebar::-webkit-scrollbar-thumb {
    background: rgba(190, 145, 255, 0.28);
    border-radius: 999px;
}

.profile-main {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding-left: 0.25rem;
    position: relative;
}

.profile-main--banned > *:not(.profile-banned-overlay) {
    filter: blur(3px);
    pointer-events: none;
    user-select: none;
    opacity: 0.45;
}

.profile-banned-overlay {
    position: absolute;
    inset: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(4, 3, 12, 0.6);
    backdrop-filter: blur(6px);
    border-radius: 8px;
}

.profile-banned-card {
    display: flex;
    flex-direction: column;
    width: min(640px, 90%);
    background: rgb(10, 8, 20);
    border: 1px solid rgba(200, 60, 60, 0.2);
    box-shadow:
        inset 0 1px 0 rgba(255, 100, 100, 0.12),
        inset 0 -1px 0 rgba(0, 0, 0, 0.4),
        0 2px 1px rgba(200, 60, 60, 0.06),
        0 24px 64px rgba(0, 0, 0, 0.7);
    overflow: hidden;
}

.profile-banned-card__stripe {
    height: 2px;
    flex-shrink: 0;
    background: linear-gradient(
        90deg,
        rgba(220, 60, 60, 0) 0%,
        rgba(220, 60, 60, 0.9) 25%,
        rgba(220, 60, 60, 0.9) 75%,
        rgba(220, 60, 60, 0) 100%
    );
}

.profile-banned-card__body {
    flex: 1;
    padding: 1.6rem 1.8rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.profile-banned-card__label {
    font-size: 0.75rem;
    font-family: "Courier New", monospace;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: rgba(200, 60, 60, 0.7);
    text-transform: uppercase;
}

.profile-banned-card__header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.profile-banned-card__icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    color: rgba(255, 100, 100, 0.75);
}

.profile-banned-card__title {
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: rgba(255, 200, 200, 0.92);
    margin: 0;
}

.profile-banned-card__sub {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.28);
    margin: 0;
    line-height: 1.5;
    letter-spacing: 0.01em;
}

/* ── Таббар (horizontal) ──────────────────────────────────── */
.profile-tabs {
    display: flex;
    flex-direction: row;
    flex-shrink: 0;
    background: transparent;
    border: none;
    padding: 0.25rem 0;
    position: relative;
    gap: 0;

    overflow-x: auto;
    scrollbar-width: none;
    margin-bottom: 0.75rem;
}
.profile-tabs::-webkit-scrollbar {
    display: none;
}

.cd-back {
    margin-left: auto;
    flex-shrink: 0;
}

.cd-back-mobile {
    display: none;
}

@media (max-width: 600px) {
    .cd-back {
        display: none;
    }
    .cd-back-mobile {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
        padding: 0.5rem 0.75rem;
        margin-bottom: 0.5rem;
        background: color-mix(
            in srgb,
            var(--cat-accent, #ffb2ef) 8%,
            transparent
        );
        border: 1px solid
            color-mix(in srgb, var(--cat-accent, #ffb2ef) 25%, transparent);
        border-radius: 6px;
        color: color-mix(in srgb, var(--cat-accent, #ffb2ef) 80%, white);
        font-size: 0.85rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition:
            background 0.15s,
            border-color 0.15s;
    }
}

.tab-btn {
    padding: 0.55rem 0.85rem;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.88rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition:
        color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease;
    white-space: nowrap;
    flex-shrink: 0;
}
.tab-icon {
    font-size: 1rem;
    flex-shrink: 0;
}
.tab-label {
    transition:
        max-width 0.2s ease,
        opacity 0.2s ease;
}
.tab-btn.active {
    color: color-mix(in srgb, var(--color-base-1), white 20%);
    background: linear-gradient(
        160deg,
        color-mix(in srgb, var(--color-base-1), transparent 82%) 0%,
        color-mix(in srgb, var(--color-base-1), transparent 90%) 100%
    );
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 85%);
    box-shadow: inset 0 1px 0
        color-mix(in srgb, var(--color-base-1), transparent 60%);
}
.tab-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.75);
}

/* ── Контент ──────────────────────────────────────────────── */
.tab-content-wrap {
    position: relative;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.tab-fade-enter-active {
    transition:
        opacity 0.22s ease,
        transform 0.22s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.tab-fade-leave-active {
    transition:
        opacity 0.16s ease,
        transform 0.16s ease-in;
}
.tab-fade-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.tab-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.tab-panel {
    height: 100%;
    overflow-y: auto;
    padding-bottom: 2rem;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--color-base-1), transparent 75%)
        transparent;
}
.tab-panel::-webkit-scrollbar {
    width: 3px;
}
.tab-panel::-webkit-scrollbar-track {
    background: transparent;
    margin-block: 0.5rem;
}
.tab-panel::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--color-base-1), transparent 72%);
    border-radius: 999px;
}

/* ── About: верхняя сетка ─────────────────────────────────── */
.about-top-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    background: #06060e;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 3px 3px 0 0;
    overflow: hidden;
    margin-bottom: 0;
}

.about-top-grid > :first-child {
    min-width: 0;
}

.about-top-grid :deep(.block-section) {
    border: none;
    border-right: 1px solid rgba(255, 255, 255, 0.18);
}

.about-voice-col {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem 1rem;
}

/* ── Слитая панель ────────────────────────────────────────── */
.fused-panel {
    background: #06060e;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-top: none;
    border-radius: 0 0 3px 3px;
    overflow: hidden;
}

.fused-panel :deep(.block-section) {
    border-top: 1px solid rgba(255, 255, 255, 0.18);
    background: #06060e;
}

.fused-panel > :first-child :deep(.block-section) {
    border-top: none;
}

/* ── Остальные табы ───────────────────────────────────────── */
.tab-panel > .anim-block + .anim-block {
    margin-top: 1rem;
}

.coming-soon-block {
    padding: 4rem 2rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}
.coming-soon-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.28);
    margin: 0 0 0.4rem;
}
.coming-soon-text {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.15);
    margin: 0;
}

/* ── Idol CTA block ──────────────────────────────────────── */
.idol-cta-block {
    position: relative;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 82%);
    border-top-color: color-mix(in srgb, var(--color-base-1), transparent 70%);
    border-radius: 6px;
    background:
        repeating-linear-gradient(
            0deg,
            transparent,
            transparent 23px,
            color-mix(in srgb, var(--color-base-1), transparent 97.5%) 24px
        ),
        linear-gradient(
            120deg,
            color-mix(in srgb, var(--color-base-1), transparent 90%) 0%,
            color-mix(in srgb, var(--color-base-1), transparent 96%) 50%,
            rgba(100, 210, 255, 0.07) 100%
        );
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 88%),
        inset 0 -1px 0 rgba(0, 0, 0, 0.22),
        0 6px 32px rgba(0, 0, 0, 0.2);
}

.idol-cta-block::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(
        ellipse 70% 100% at 100% 50%,
        rgba(100, 210, 255, 0.08) 0%,
        transparent 70%
    );
    pointer-events: none;
}

.idol-cta-content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 2rem;
    min-height: 140px;
}

.idol-cta-left {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.idol-cta-eyebrow {
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: var(--color-base-1);
    opacity: 0.5;
}

.idol-cta-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.92);
    margin: 0;
    line-height: 1.15;
    letter-spacing: -0.02em;
}

.idol-cta-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.55rem;
}

.idol-cta-tag {
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.05em;
    color: var(--color-base-1);
    opacity: 0.8;
    background: color-mix(in srgb, var(--color-base-1), transparent 92%);
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 80%);
    border-radius: 3px;
    padding: 0.2rem 0.55rem;
    box-shadow: inset 0 1px 0
        color-mix(in srgb, var(--color-base-1), transparent 92%);
}

.idol-cta-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    padding: 0.65rem 1.35rem;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 70%);
    border-radius: 4px;
    background: color-mix(in srgb, var(--color-base-1), transparent 91%);
    color: var(--color-base-1);
    font-size: 0.74rem;
    font-weight: 600;
    letter-spacing: 0.13em;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 90%),
        0 2px 12px color-mix(in srgb, var(--color-base-1), transparent 92%);
    transition:
        background 0.2s,
        border-color 0.2s,
        color 0.2s,
        box-shadow 0.2s,
        transform 0.15s;
}

.idol-cta-btn:hover {
    background: color-mix(in srgb, var(--color-base-1), transparent 84%);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 45%);
    color: color-mix(in srgb, var(--color-base-1), white 20%);
    box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--color-base-1), transparent 85%),
        0 0 20px color-mix(in srgb, var(--color-base-1), transparent 82%),
        0 4px 18px rgba(0, 0, 0, 0.25);
    transform: translateY(-1px);
}

@media (max-width: 600px) {
    .idol-cta-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.25rem;
        padding: 1.5rem;
        min-height: auto;
    }
    .idol-cta-title {
        font-size: 1.15rem;
    }
    .idol-cta-btn {
        width: 100%;
        justify-content: center;
    }
}

.sidebar-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.65rem;
}
.sidebar-subscribe-btn {
    width: 100%;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    border-radius: 6px;
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-2), transparent 90%),
        color-mix(in srgb, var(--color-base-2), transparent 96%)
    );
    border: 1px solid color-mix(in srgb, var(--color-base-2), transparent 60%);
    color: var(--color-base-2);
    font-family: inherit;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
}
.sidebar-subscribe-btn:hover {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-2), transparent 82%),
        color-mix(in srgb, var(--color-base-2), transparent 92%)
    );
    border-color: color-mix(in srgb, var(--color-base-2), transparent 30%);
    box-shadow:
        0 4px 12px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}
.sidebar-subscribe-btn:active {
    transform: translateY(0) scale(0.98);
}
.sidebar-subscribe-btn--active {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 85%),
        color-mix(in srgb, var(--color-base-1), transparent 92%)
    );
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 50%);
    color: var(--color-base-1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.sidebar-subscribe-btn--active:hover {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 75%),
        color-mix(in srgb, var(--color-base-1), transparent 85%)
    );
    border-color: color-mix(in srgb, var(--color-base-1), transparent 20%);
}

/* ── Unfollow confirm ────────────────────────────────────── */
.unfollow-confirm {
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
    gap: 1.25rem;
}
.unfollow-confirm__body {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    text-align: left;
}
.unfollow-confirm__icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--color-base-1), transparent 90%);
    color: var(--color-base-1);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.unfollow-confirm__content {
    flex: 1;
}
.unfollow-confirm__title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.2rem;
    font-family: "Rubik", sans-serif;
}
.unfollow-confirm__text {
    font-size: 0.85rem;
    line-height: 1.4;
    color: color-mix(in srgb, #fff, transparent 40%);
}
.unfollow-confirm__actions {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
}
.unfollow-confirm__btn {
    padding: 0 1.5rem;
    height: 38px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.unfollow-confirm__btn--cancel {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: color-mix(in srgb, #fff, transparent 20%);
}
.unfollow-confirm__btn--cancel:hover {
    background: rgba(255, 255, 255, 0.05);
}
.unfollow-confirm__btn--confirm {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 85%),
        color-mix(in srgb, var(--color-base-1), transparent 92%)
    );
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 50%);
    color: var(--color-base-1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.unfollow-confirm__btn--confirm:hover {
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--color-base-1), transparent 75%),
        color-mix(in srgb, var(--color-base-1), transparent 85%)
    );
    border-color: color-mix(in srgb, var(--color-base-1), transparent 20%);
}

.sidebar-message-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    height: 40px;
    border-radius: 6px;
    background: transparent;
    border: 1px solid color-mix(in srgb, var(--color-base-1), transparent 65%);
    color: var(--color-base-1);
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sidebar-message-btn:hover {
    background: color-mix(in srgb, var(--color-base-1), transparent 90%);
    border-color: color-mix(in srgb, var(--color-base-1), transparent 45%);
    box-shadow: 0 0 14px
        color-mix(in srgb, var(--color-base-1), transparent 88%);
}

/* ── Report modal content ────────────────────────────────── */
.report-form,
.report-success {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}
.report-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.25rem;
    font-family: "Rubik", sans-serif;
}
.report-field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.report-label {
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--color-base-1), transparent 40%);
}
.report-optional {
    text-transform: none;
    letter-spacing: 0;
    opacity: 0.6;
}
.report-reasons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.report-reason-btn {
    padding: 0.32rem 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.45);
    font-family: "Rubik", sans-serif;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.15s;
    border-radius: 2px;
}
.report-reason-btn:hover {
    border-color: rgba(239, 68, 68, 0.35);
    color: rgba(255, 255, 255, 0.8);
}
.report-reason-btn--active {
    border-color: rgba(239, 68, 68, 0.55);
    background: rgba(239, 68, 68, 0.09);
    color: #f87171;
}
.report-textarea {
    padding: 0.55rem 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.85);
    font-family: "Rubik", sans-serif;
    font-size: 0.9rem;
    outline: none;
    resize: vertical;
    min-height: 80px;
    transition: border-color 0.15s;
    box-sizing: border-box;
    width: 100%;
    border-radius: 2px;
}
.report-textarea:focus {
    border-color: color-mix(in srgb, var(--color-base-1), transparent 65%);
}
.report-textarea--err {
    border-color: rgba(239, 68, 68, 0.5);
}
.report-textarea::placeholder {
    color: rgba(255, 255, 255, 0.18);
}
.report-err {
    font-size: 0.75rem;
    color: rgba(239, 68, 68, 0.75);
    margin: 0;
}
.report-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 0.25rem;
}
.report-btn-cancel {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: transparent;
    color: rgba(255, 255, 255, 0.35);
    font-family: "Rubik", sans-serif;
    font-size: 0.85rem;
    cursor: pointer;
    border-radius: 2px;
    transition:
        color 0.15s,
        border-color 0.15s;
}
.report-btn-cancel:hover {
    color: rgba(255, 255, 255, 0.6);
    border-color: rgba(255, 255, 255, 0.2);
}
.report-btn-submit {
    padding: 0.5rem 1.25rem;
    border: 1px solid rgba(239, 68, 68, 0.4);
    background: rgba(239, 68, 68, 0.08);
    color: rgba(255, 255, 255, 0.88);
    font-family: "Rubik", sans-serif;
    font-size: 0.85rem;
    cursor: pointer;
    border-radius: 2px;
    transition:
        background 0.15s,
        border-color 0.15s;
}
.report-btn-submit:hover:not(:disabled) {
    background: rgba(239, 68, 68, 0.18);
    border-color: rgba(239, 68, 68, 0.6);
}
.report-btn-submit:disabled {
    opacity: 0.3;
    cursor: default;
}

.report-success {
    align-items: center;
    text-align: center;
    padding: 1.5rem 0;
    color: rgba(74, 222, 128, 0.8);
}
.report-success p {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
    line-height: 1.6;
}
.report-btn-close {
    margin-top: 0.5rem;
    padding: 0.5rem 1.5rem;
    border: 1px solid rgba(74, 222, 128, 0.3);
    background: rgba(74, 222, 128, 0.06);
    color: rgba(74, 222, 128, 0.8);
    font-family: "Rubik", sans-serif;
    font-size: 0.85rem;
    cursor: pointer;
    border-radius: 2px;
    transition: background 0.15s;
}
.report-btn-close:hover {
    background: rgba(74, 222, 128, 0.14);
}

/* ── Скелетоны (deferred fallback) ───────────────────────── */
@keyframes shimmer {
    0% {
        background-position: -400px 0;
    }
    100% {
        background-position: 400px 0;
    }
}

.skeleton-row {
    height: 64px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.04) 25%,
        rgba(255, 255, 255, 0.08) 50%,
        rgba(255, 255, 255, 0.04) 75%
    );
    background-size: 800px 100%;
    animation: shimmer 1.4s infinite linear;
}
.skeleton-row:first-child {
    border-top: none;
    height: 72px;
}
.skeleton-row--mid {
    height: 88px;
}
.skeleton-row--short {
    height: 56px;
}

/* ── Адаптив ──────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .profile-sidebar {
        width: 300px;
    }
}
@media (max-width: 900px) {
    .profile-sidebar {
        width: 240px;
    }
}
@media (max-width: 600px) {
    .profile-tabs {
        gap: 0.2rem;
    }
    .tab-btn {
        flex: 1;
        flex-direction: column;
        gap: 0.2rem;
        padding: 0.5rem 0.25rem;
        font-size: 0.62rem;
        letter-spacing: 0.03em;
    }
    .tab-icon {
        font-size: 1.25rem;
    }
}

@media (max-width: 768px) {
    .profile-page {
        height: auto;
        overflow: visible;
        padding: 0 1rem;
    }
    .profile-container {
        height: auto;
    }
    .profile-body {
        flex-direction: column;
    }
    .profile-sidebar {
        width: 100%;
        overflow: visible;
        border-right: none;
        padding-right: 0;
    }
    .profile-main {
        padding-left: 0;
        height: auto;
        min-height: 40vh;
    }
    .tab-content-wrap {
        overflow: visible;
        min-height: unset;
    }
    .tab-panel {
        height: auto;
        overflow: visible;
        padding-bottom: 3rem;
    }
}

@media (max-width: 700px) {
    .about-top-grid {
        grid-template-columns: 1fr;
    }
    .about-top-grid :deep(.block-section) {
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    }
    .about-voice-col {
        padding: 1.25rem;
    }
}
</style>
