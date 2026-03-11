<script setup>
import { computed, ref, shallowRef } from 'vue';
import {
    QuestionFilled,
    User,
    ChatDotRound,
    Setting,
    Lock,
    InfoFilled,
} from '@element-plus/icons-vue';
import SiteModal from './SiteModal.vue';
import FaqItem from './FaqItem.vue';
import FaqDetail from './FaqDetail.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const faqCategories = [
    {
        id: 1,
        title: 'Что такое no alone?',
        icon: QuestionFilled,
        questions: [
            {
                id: 1,
                q: 'О сервисе',
                a: '<p>no alone — это сервис для поиска собеседников и живого общения. Здесь вы можете найти людей со схожими интересами и просто поговорить.</p><p>Мы верим, что каждый человек заслуживает внимания и живого общения.</p>',
            },
            {
                id: 2,
                q: 'Для кого это?',
                a: '<p>Сервис подходит для всех, кто хочет найти нового собеседника, поделиться мыслями или просто не быть одному. Неважно, сколько вам лет и где вы находитесь.</p>',
            },
            {
                id: 3,
                q: 'Это бесплатно?',
                a: '<p>Базовые функции сервиса полностью бесплатны. Вы можете общаться без ограничений и без скрытых платежей.</p><p>Расширенные возможности могут быть доступны по подписке.</p>',
            },
            {
                id: 4,
                q: 'Нужно ли устанавливать приложение?',
                a: '<p>Нет, no alone работает прямо в браузере. Для удобства вы также можете установить мобильное приложение — оно доступно в App Store и Google Play.</p>',
            },
        ],
    },
    {
        id: 2,
        title: 'Регистрация',
        icon: User,
        questions: [
            {
                id: 1,
                q: 'Как создать аккаунт?',
                a: '<p>Нажмите кнопку «Начать» на главной странице. Введите email и придумайте пароль. После подтверждения email ваш аккаунт будет активирован — это займёт меньше минуты.</p>',
            },
            {
                id: 2,
                q: 'Забыл пароль — что делать?',
                a: '<p>На странице входа нажмите «Забыл пароль». Введите email, привязанный к аккаунту, и мы отправим вам ссылку для сброса пароля.</p>',
            },
            {
                id: 3,
                q: 'Как удалить аккаунт?',
                a: '<p>Вы можете удалить аккаунт в разделе «Настройки → Аккаунт → Удалить аккаунт». После удаления все данные будут безвозвратно стёрты в течение 30 дней.</p>',
            },
        ],
    },
    {
        id: 3,
        title: 'Общение',
        icon: ChatDotRound,
        questions: [
            {
                id: 1,
                q: 'Как найти собеседника?',
                a: '<p>После входа перейдите в раздел «Поиск». Можно фильтровать по интересам, возрасту и другим параметрам. Отправьте запрос на общение — и начните разговор!</p>',
            },
            {
                id: 2,
                q: 'Можно ли отказать собеседнику?',
                a: '<p>Да, общение добровольно. Вы можете завершить беседу в любой момент или отклонить входящий запрос без объяснения причин.</p>',
            },
            {
                id: 3,
                q: 'Что если собеседник нарушает правила?',
                a: '<p>Нажмите на профиль пользователя и выберите «Пожаловаться». Мы рассмотрим жалобу в течение 24 часов. Вы также можете сразу заблокировать этого человека.</p>',
            },
            {
                id: 4,
                q: 'Сколько человек можно добавить в контакты?',
                a: '<p>На бесплатном тарифе — до 50 контактов. С подпиской лимит снимается, и вы можете добавлять неограниченное количество людей.</p>',
            },
        ],
    },
    {
        id: 4,
        title: 'Настройки',
        icon: Setting,
        questions: [
            {
                id: 1,
                q: 'Как изменить профиль?',
                a: '<p>Перейдите в «Настройки → Профиль». Здесь можно изменить аватар, имя, описание и список интересов. Изменения сохраняются автоматически.</p>',
            },
            {
                id: 2,
                q: 'Как настроить уведомления?',
                a: '<p>В разделе «Настройки → Уведомления» выберите, о чём хотите получать оповещения: новые сообщения, запросы на общение, системные новости.</p>',
            },
            {
                id: 3,
                q: 'Как управлять приватностью?',
                a: '<p>В «Настройки → Приватность» вы управляете видимостью профиля: кто может вас найти, видеть онлайн-статус и отправлять запросы на общение.</p>',
            },
        ],
    },
    {
        id: 5,
        title: 'Безопасность',
        icon: Lock,
        questions: [
            {
                id: 1,
                q: 'Как защищены мои данные?',
                a: '<p>Все данные передаются по зашифрованному соединению (HTTPS/TLS). Мы не продаём личные данные третьим лицам и соблюдаем требования GDPR.</p>',
            },
            {
                id: 2,
                q: 'Как заблокировать пользователя?',
                a: '<p>Откройте профиль пользователя и нажмите «Заблокировать». После этого он не сможет видеть ваш профиль, писать вам или добавлять вас в контакты.</p>',
            },
            {
                id: 3,
                q: 'Как подать жалобу?',
                a: '<p>На странице профиля пользователя или в активном чате нажмите «⋯ → Пожаловаться». Выберите причину и при желании добавьте комментарий. Мы рассмотрим в течение 24 часов.</p>',
            },
        ],
    },
    {
        id: 6,
        title: 'Контакты',
        icon: InfoFilled,
        questions: [
            {
                id: 1,
                q: 'Как связаться с поддержкой?',
                a: '<p>Напишите нам на <strong>support@noalone.app</strong> — мы отвечаем в течение 24 часов в рабочие дни. Также можно воспользоваться формой обратной связи внутри приложения.</p>',
            },
            {
                id: 2,
                q: 'Где вы в соцсетях?',
                a: '<p>Мы есть в Telegram, ВКонтакте и Instagram. Ссылки на актуальные страницы можно найти в подвале сайта.</p>',
            },
            {
                id: 3,
                q: 'Как стать партнёром?',
                a: '<p>По вопросам сотрудничества и партнёрства пишите на <strong>partners@noalone.app</strong>. Расскажите о вашем проекте, и мы рассмотрим предложение.</p>',
            },
        ],
    },
];

const activeCategory = shallowRef(faqCategories[0]);
const activeIndex = computed(() =>
    faqCategories.findIndex((c) => c === activeCategory.value) + 1,
);

function setCategory(cat) {
    activeCategory.value = cat;
}
</script>

<template>
    <SiteModal :show="show" variant="pink" @close="emit('close')">
        <div class="faq-layout">

            <!-- ── Sidebar ── -->
            <nav class="faq-sidebar">
                <div class="faq-sidebar__label">Разделы</div>
                <FaqItem
                    v-for="cat in faqCategories"
                    :key="cat.id"
                    :title="cat.title"
                    :active="cat === activeCategory"
                    @click="setCategory(cat)"
                >
                    <template #icon>
                        <component :is="cat.icon" class="faq-cat-icon" />
                    </template>
                </FaqItem>
            </nav>

            <!-- ── Divider ── -->
            <div class="faq-divider" />

            <!-- ── Content ── -->
            <div class="faq-content">
                <Transition name="panel-fade" mode="out-in">
                    <div :key="activeCategory.id" class="faq-content-inner">
                        <div class="faq-content__header">
                            <span class="faq-content__counter">
                                {{ String(activeIndex).padStart(2, '0') }} / {{ String(faqCategories.length).padStart(2, '0') }}
                            </span>
                            <h3 class="faq-content__title">{{ activeCategory.title }}</h3>
                        </div>
                        <div class="faq-accordions">
                            <FaqDetail
                                v-for="item in activeCategory.questions"
                                :key="item.id"
                                :question="item.q"
                                :answer="item.a"
                            />
                        </div>
                    </div>
                </Transition>
            </div>

        </div>
    </SiteModal>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Imbue:opsz,wght@10..100,100;10..100,200;10..100,300&display=swap');

.faq-layout {
    display: flex;
    flex-direction: row;
    height: 100%;
    color: #fff;
    min-height: 0;
}

/* ── Sidebar ─────────────────────────────────── */
.faq-sidebar {
    width: 37%;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    overflow-y: auto;
    padding: 0 0 0.5rem;
    background: linear-gradient(
        180deg,
        rgba(155, 110, 232, 0.06) 0%,
        rgba(155, 110, 232, 0.02) 40%,
        transparent 100%
    );
    border-radius: 3px 0 0 3px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.06) transparent;
}

.faq-sidebar::-webkit-scrollbar { width: 3px; }
.faq-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 3px;
}

.faq-sidebar__label {
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(155, 110, 232, 0.45);
    padding: 0.5rem 0.9rem;
    margin-bottom: 0.15rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

/* ── Divider ─────────────────────────────────── */
.faq-divider {
    width: 1px;
    flex-shrink: 0;
    margin: 0 0.6rem;
    background: linear-gradient(
        to bottom,
        transparent,
        rgba(255, 255, 255, 0.08) 15%,
        rgba(255, 255, 255, 0.08) 85%,
        transparent
    );
}

/* ── Content ─────────────────────────────────── */
.faq-content {
    flex: 1;
    overflow-y: auto;
    min-width: 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.06) transparent;
}

.faq-content::-webkit-scrollbar { width: 3px; }
.faq-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 3px;
}

.faq-content-inner {
    padding: 0 0.5rem 0.5rem;
}

.faq-content__header {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.faq-content__counter {
    display: block;
    font-family: 'Courier New', 'Courier', monospace;
    font-size: 0.65rem;
    letter-spacing: 0.14em;
    color: rgba(155, 110, 232, 0.55);
    margin-bottom: 0.3rem;
}

.faq-content__title {
    font-family: 'Imbue', serif;
    font-size: 1.55rem;
    font-weight: 200;
    letter-spacing: 0.02em;
    line-height: 1.2;
    color: rgba(255, 255, 255, 0.92);
    text-shadow:
        0 0 25px rgba(155, 110, 232, 0.35),
        0 0 60px rgba(155, 110, 232, 0.15);
}

.faq-accordions {
    display: flex;
    flex-direction: column;
}

.faq-cat-icon {
    width: 16px;
    height: 16px;
}

/* ── Panel fade ──────────────────────────────── */
.panel-fade-enter-active,
.panel-fade-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.panel-fade-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}
.panel-fade-enter-from {
    opacity: 0;
    transform: translateX(10px);
}

/* ── Large screens ───────────────────────────── */
@media (min-width: 1440px) {
    .faq-cat-icon { width: 19px; height: 19px; }
    .faq-content__title { font-size: 1.85rem; }
    .faq-content__counter { font-size: 0.72rem; }
    .faq-sidebar__label { font-size: 0.68rem; }
    .faq-divider { margin: 0 0.85rem; }
}

@media (min-width: 2000px) {
    .faq-cat-icon { width: 22px; height: 22px; }
    .faq-content__title { font-size: 2.1rem; }
    .faq-content__counter { font-size: 0.78rem; }
    .faq-divider { margin: 0 1rem; }
}

/* ── Mobile ──────────────────────────────────── */
@media (max-width: 767px) {
    .faq-layout {
        flex-direction: column;
        gap: 0.75rem;
    }

    .faq-sidebar {
        width: 100%;
        flex-direction: row;
        flex-shrink: 0;
        overflow-x: auto;
        overflow-y: hidden;
        gap: 0.45rem;
        padding: 0.15rem 0 0.35rem;
        background: none;
        border-radius: 0;
        scrollbar-width: none;
    }

    .faq-sidebar::-webkit-scrollbar { display: none; }
    .faq-sidebar__label { display: none; }
    .faq-divider { display: none; }

    .faq-content { flex: 1; }

    .faq-content-inner { padding: 0; }

    .faq-content__title {
        font-size: 1.25rem;
        text-shadow: 0 0 18px rgba(155, 110, 232, 0.3);
    }
}
</style>
