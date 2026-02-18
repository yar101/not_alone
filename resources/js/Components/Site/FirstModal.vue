<script setup>
import { ref } from 'vue';
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

const activeCategory = ref(faqCategories[0]);

function setCategory(cat) {
    activeCategory.value = cat;
}
</script>

<template>
    <SiteModal :show="show" variant="pink" @close="emit('close')">
        <div class="faq-layout">
            <!-- Sidebar (desktop) / Tab bar (mobile) -->
            <nav class="faq-sidebar">
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

            <!-- Divider (desktop only) -->
            <div class="faq-divider" />

            <!-- Content panel -->
            <div class="faq-content">
                <Transition name="panel-fade" mode="out-in">
                    <div :key="activeCategory.id" class="faq-content-inner">
                        <h3 class="faq-content__title">{{ activeCategory.title }}</h3>
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
.faq-layout {
    display: flex;
    flex-direction: row;
    height: 100%;
    color: #fff;
    min-height: 0;
}

/* ── Sidebar ─────────────────────────────────── */
.faq-sidebar {
    width: 38%;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    overflow-y: auto;
    padding: 0.25rem 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}

.faq-sidebar::-webkit-scrollbar {
    width: 3px;
}
.faq-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
}

/* ── Divider ─────────────────────────────────── */
.faq-divider {
    width: 1px;
    background: rgba(255, 255, 255, 0.07);
    flex-shrink: 0;
    margin: 0 0.5rem;
}

/* ── Content ─────────────────────────────────── */
.faq-content {
    flex: 1;
    overflow-y: auto;
    min-width: 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}

.faq-content::-webkit-scrollbar {
    width: 3px;
}
.faq-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
}

.faq-content-inner {
    padding: 0.25rem 0.5rem 0.5rem;
}

.faq-content__title {
    font-size: 1rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 0.75rem;
    letter-spacing: 0.02em;
}

.faq-accordions {
    display: flex;
    flex-direction: column;
}

/* ── Panel fade transition ───────────────────── */
.panel-fade-enter-active,
.panel-fade-leave-active {
    transition: opacity 0.16s ease, transform 0.16s ease;
}

.panel-fade-leave-to {
    opacity: 0;
    transform: translateX(-8px);
}

.panel-fade-enter-from {
    opacity: 0;
    transform: translateX(8px);
}

.faq-cat-icon {
    width: 18px;
    height: 18px;
}

@media (min-width: 1440px) {
    .faq-cat-icon {
        width: 22px;
        height: 22px;
    }
}

@media (min-width: 2000px) {
    .faq-cat-icon {
        width: 24px;
        height: 24px;
    }
}

/* ── Large screens ───────────────────────────── */
@media (min-width: 1440px) {
    .faq-content__title {
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    .faq-divider {
        margin: 0 0.75rem;
    }
}

@media (min-width: 2000px) {
    .faq-content__title {
        font-size: 1.35rem;
        margin-bottom: 1.2rem;
    }

    .faq-divider {
        margin: 0 1rem;
    }
}

/* ── Mobile ──────────────────────────────────── */
@media (max-width: 767px) {
    .faq-layout {
        flex-direction: column;
        gap: 1rem;
    }

    .faq-sidebar {
        width: 100%;
        flex-direction: row;
        flex-shrink: 0;
        overflow-x: auto;
        overflow-y: hidden;
        gap: 0.5rem;
        padding: 0.25rem 0;
        scrollbar-width: none;
    }

    .faq-sidebar::-webkit-scrollbar {
        display: none;
    }

    .faq-divider {
        display: none;
    }

    .faq-content {
        flex: 1;
    }

    .faq-content-inner {
        padding: 0;
    }
}
</style>
