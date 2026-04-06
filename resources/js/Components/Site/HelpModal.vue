<script setup>
import { computed, ref, shallowRef, onUnmounted } from 'vue';
import axios from 'axios';
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
    showDispute: {
        type: Boolean,
        default: true,
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
const activeQuestion = ref(null);
const activeIndex = computed(() =>
    faqCategories.findIndex((c) => c === activeCategory.value) + 1,
);

function setCategory(cat) {
    disputeView.value    = false;
    activeCategory.value = cat;
    activeQuestion.value = null;
}

function openQuestion(item) {
    activeQuestion.value = item;
}

function backToList() {
    activeQuestion.value = null;
}

// ── Dispute form ──────────────────────────────────────────
const DISPUTE_REASONS = [
    'Непристойное поведение',
    'Оскорбления',
    'Мошенничество',
    'Заказ не выполнен',
    'Угрозы',
    'Спам и навязывание',
];

const disputeView        = ref(false);
const disputableOrders   = ref([]);
const disputeOrderId     = ref(null);
const disputeReason      = ref('');
const disputeDetails     = ref('');
const disputeSubmitting  = ref(false);
const disputeSuccess     = ref(false);
const disputeErrors      = ref({});
const disputeLoading     = ref(false);

// Реактивный тик для таймеров в карточках заказов
const nowTick = ref(Date.now());
let tickInterval = null;

async function openDisputeForm() {
    activeCategory.value  = null;
    disputeLoading.value  = true;
    disputeView.value     = true;
    disputeSuccess.value  = false;
    disputeErrors.value   = {};
    disputeReason.value   = '';
    disputeDetails.value  = '';
    try {
        const res = await axios.get(route('orders.disputable'));
        disputableOrders.value = res.data;
        disputeOrderId.value   = res.data[0]?.id ?? null;
        if (res.data.length > 0) {
            tickInterval = setInterval(() => { nowTick.value = Date.now(); }, 1000);
        }
    } catch {
        disputeErrors.value = { _general: 'Не удалось загрузить заказы' };
    } finally {
        disputeLoading.value = false;
    }
}

function orderTimeLeft(completedAt) {
    const deadline = new Date(completedAt).getTime() + 3600 * 1000;
    const diff = deadline - nowTick.value;
    if (diff <= 0) return 'истекает…';
    const m = Math.floor(diff / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return `осталось ${m}:${s.toString().padStart(2, '0')}`;
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

onUnmounted(() => { clearInterval(tickInterval); });

async function submitDispute() {
    if (!disputeOrderId.value || !disputeReason.value || disputeDetails.value.trim().length < 100) return;
    disputeSubmitting.value = true;
    disputeErrors.value     = {};
    try {
        await axios.post(route('orders.dispute', disputeOrderId.value), {
            reason:  disputeReason.value,
            details: disputeDetails.value,
        });
        clearInterval(tickInterval);
        disputeSuccess.value = true;
    } catch (e) {
        const errs = e.response?.data?.errors ?? {};
        if (Object.keys(errs).length) {
            disputeErrors.value = Object.fromEntries(
                Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
            );
        } else {
            disputeErrors.value = { _general: e.response?.data?.message ?? 'Ошибка при отправке' };
        }
    } finally {
        disputeSubmitting.value = false;
    }
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

                <div class="faq-sidebar__actions">
                    <button class="faq-action-btn faq-action-btn--support">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Техподдержка
                    </button>
                    <button v-if="showDispute" class="faq-action-btn faq-action-btn--dispute" :class="{ 'faq-action-btn--dispute-active': disputeView }" @click="openDisputeForm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        Оспорить заказ
                    </button>
                </div>
            </nav>

            <!-- ── Divider ── -->
            <div class="faq-divider" />

            <!-- ── Content ── -->
            <div class="faq-content">
                <Transition name="panel-fade" mode="out-in">
                    <!-- Dispute form view -->
                    <div v-if="disputeView" key="dispute" class="faq-content-inner">
                        <div class="faq-content__header">
                            <span class="faq-content__counter">— / —</span>
                            <h3 class="faq-content__title">Оспорить заказ</h3>
                        </div>

                        <div v-if="disputeLoading" class="dispute-loading">Загрузка…</div>

                        <div v-else-if="disputeSuccess" class="dispute-success">
                            <p>Спор отправлен. Мы рассмотрим в течение 24 часов.</p>
                        </div>

                        <div v-else-if="disputableOrders.length === 0" class="dispute-empty">
                            <p>Нет заказов, доступных для оспаривания.<br>
                            Спор можно открыть в течение 1 часа после завершения заказа.</p>
                        </div>

                        <div v-else class="dispute-form">
                            <p v-if="disputeErrors._general" class="dispute-field-error">{{ disputeErrors._general }}</p>

                            <label class="dispute-label">Заказ</label>
                            <div class="dispute-orders-list">
                                <div
                                    v-for="o in disputableOrders"
                                    :key="o.id"
                                    class="dispute-order-card"
                                    :class="{ 'dispute-order-card--active': disputeOrderId === o.id }"
                                    @click="disputeOrderId = o.id"
                                >
                                    <div class="dispute-order-card__top">
                                        <span class="dispute-order-card__num">#{{ o.id }}</span>
                                        <span class="dispute-order-card__idol">{{ o.idol_name }}</span>
                                        <span class="dispute-order-card__total">{{ o.total }} ₽</span>
                                        <span class="dispute-order-card__timer">{{ orderTimeLeft(o.completed_at) }}</span>
                                    </div>
                                    <div class="dispute-order-card__dates">
                                        <span>Создан: {{ fmtDate(o.created_at) }}</span>
                                        <span>Выполнен: {{ fmtDate(o.completed_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <label class="dispute-label" style="margin-top:0.5rem;">Причина</label>
                            <select
                                v-model="disputeReason"
                                class="dispute-select"
                                @change="delete disputeErrors.reason"
                            >
                                <option value="">— выберите причину —</option>
                                <option v-for="r in DISPUTE_REASONS" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <p v-if="disputeErrors.reason" class="dispute-field-error">{{ disputeErrors.reason }}</p>

                            <label class="dispute-label">Детали (мин. 100 символов)</label>
                            <textarea
                                v-model="disputeDetails"
                                class="dispute-textarea"
                                placeholder="Опишите ситуацию подробнее…"
                                rows="4"
                                maxlength="2000"
                                @input="delete disputeErrors.details"
                            ></textarea>
                            <span class="dispute-charcount" :class="{ 'dispute-charcount--warn': disputeDetails.trim().length > 0 && disputeDetails.trim().length < 100 }">
                                {{ disputeDetails.trim().length }} / мин. 100
                            </span>
                            <p v-if="disputeErrors.details" class="dispute-field-error">{{ disputeErrors.details }}</p>

                            <button
                                class="dispute-submit"
                                :disabled="!disputeOrderId || !disputeReason || disputeDetails.trim().length < 100 || disputeSubmitting"
                                @click="submitDispute"
                            >{{ disputeSubmitting ? 'Отправка…' : 'Отправить спор' }}</button>
                        </div>
                    </div>

                    <!-- FAQ view — список подразделов -->
                    <div v-else-if="!activeQuestion" :key="`cat-${activeCategory.id}`" class="faq-content-inner">
                        <div class="faq-content__header">
                            <span class="faq-content__counter">
                                {{ String(activeIndex).padStart(2, '0') }} / {{ String(faqCategories.length).padStart(2, '0') }}
                            </span>
                            <h3 class="faq-content__title">{{ activeCategory.title }}</h3>
                        </div>
                        <div class="faq-subsections">
                            <button
                                v-for="item in activeCategory.questions"
                                :key="item.id"
                                class="faq-subsection-item"
                                @click="openQuestion(item)"
                            >
                                <span class="faq-subsection-item__title">{{ item.q }}</span>
                                <svg class="faq-subsection-item__arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- FAQ view — содержимое подраздела -->
                    <div v-else :key="`q-${activeQuestion.id}`" class="faq-content-inner">
                        <div class="faq-content__header faq-content__header--with-back">
                            <button class="faq-back-btn" @click="backToList">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 18l-6-6 6-6"/>
                                </svg>
                                Назад
                            </button>
                            <h3 class="faq-content__title">{{ activeQuestion.q }}</h3>
                        </div>
                        <div class="faq-answer-body" v-html="activeQuestion.a" />
                    </div>
                </Transition>
            </div>

            <!-- ── Mobile footer actions ── -->
            <div class="faq-footer-actions">
                <button class="faq-action-btn faq-action-btn--support">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Техподдержка
                </button>
                <button v-if="showDispute" class="faq-action-btn faq-action-btn--dispute" @click="openDisputeForm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Оспорить заказ
                </button>
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
    width: 37%;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    overflow-y: auto;
    padding: 0 0 0.5rem;
    background: linear-gradient(
        180deg,
        rgba(110, 110, 210, 0.06) 0%,
        rgba(110, 110, 210, 0.02) 40%,
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
    color: rgba(110, 110, 210, 0.45);
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
    color: rgba(110, 110, 210, 0.55);
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
        0 0 25px rgba(110, 110, 210, 0.35),
        0 0 60px rgba(110, 110, 210, 0.15);
}

.faq-accordions {
    display: flex;
    flex-direction: column;
}

/* Subsections list */
.faq-subsections {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.faq-subsection-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.8rem 0.75rem;
    background: transparent;
    border: none;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    cursor: pointer;
    text-align: left;
    transition: background 0.15s, color 0.15s;
    border-radius: 4px;
}
.faq-subsection-item:last-child { border-bottom: none; }
.faq-subsection-item:hover {
    background: rgba(110,110,210,0.06);
}
.faq-subsection-item:hover .faq-subsection-item__arrow {
    color: rgba(110,110,210,0.8);
    transform: translateX(2px);
}

.faq-subsection-item__title {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.65);
    line-height: 1.4;
    transition: color 0.15s;
}
.faq-subsection-item:hover .faq-subsection-item__title {
    color: rgba(255,255,255,0.9);
}

.faq-subsection-item__arrow {
    color: rgba(255,255,255,0.2);
    flex-shrink: 0;
    transition: color 0.15s, transform 0.15s;
}

/* Back button + answer */
.faq-content__header--with-back {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
}

.faq-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: none;
    border: none;
    cursor: pointer;
    color: rgba(255,255,255,0.3);
    font-size: 0.75rem;
    font-family: inherit;
    padding: 0;
    transition: color 0.15s;
}
.faq-back-btn:hover { color: rgba(110,110,210,0.85); }

.faq-answer-body {
    font-size: 1rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.75;
}
.faq-answer-body :deep(p) { margin-bottom: 0.65rem; }
.faq-answer-body :deep(p:last-child) { margin-bottom: 0; }
.faq-answer-body :deep(strong) { color: rgba(255,255,255,0.75); font-weight: 500; }
.faq-answer-body :deep(a) { color: rgba(110,110,210,0.85); text-decoration: none; transition: color 0.15s; }
.faq-answer-body :deep(a:hover) { color: rgba(160,150,255,0.9); text-decoration: underline; }

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

/* ── Action buttons ──────────────────────────── */
.faq-sidebar__actions {
    padding: 0.75rem 0.5rem 0.5rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.faq-action-btn {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.55rem 0.75rem;
    border-radius: 3px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(255, 255, 255, 0.45);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.faq-action-btn svg { flex-shrink: 0; opacity: 0.7; }

.faq-action-btn--support:hover {
    background: rgba(110, 110, 210, 0.1);
    border-color: rgba(110, 110, 210, 0.3);
    color: rgba(160, 140, 255, 0.9);
}
.faq-action-btn--dispute:hover {
    background: rgba(255, 110, 110, 0.07);
    border-color: rgba(255, 110, 110, 0.25);
    color: rgba(255, 130, 130, 0.85);
}
.faq-action-btn--dispute-active {
    background: rgba(255, 110, 110, 0.09);
    border-color: rgba(255, 110, 110, 0.35);
    color: rgba(255, 130, 130, 0.9);
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
    .faq-sidebar__actions { display: none; }

    .faq-content { flex: 1; }

    .faq-content-inner { padding: 0; }

    .faq-content__title {
        font-size: 1.25rem;
        text-shadow: 0 0 18px rgba(110, 110, 210, 0.3);
    }

    .faq-footer-actions {
        display: flex;
        gap: 0.5rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        flex-shrink: 0;
    }
}

/* hide mobile footer on desktop */
.faq-footer-actions { display: none; }

/* ── Dispute form ─────────────────────────── */
.dispute-loading,
.dispute-empty {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.45);
    padding: 1rem 0;
    line-height: 1.6;
}

.dispute-success {
    font-size: 0.85rem;
    color: rgba(80,240,160,0.88);
    padding: 1rem 0;
    line-height: 1.6;
}

.dispute-error {
    font-size: 0.8rem;
    color: rgba(255,110,110,0.85);
    margin-bottom: 0.75rem;
}

.dispute-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.dispute-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(255,255,255,0.4);
    margin-top: 0.25rem;
}

.dispute-select,
.dispute-textarea {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(110,110,210,0.25);
    border-radius: 3px;
    color: rgba(255,255,255,0.85);
    font-size: 0.82rem;
    padding: 0.5rem 0.65rem;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
}

.dispute-select:focus,
.dispute-textarea:focus {
    border-color: rgba(110,110,210,0.55);
}

.dispute-select option {
    background: #1a1a2e;
    color: rgba(255,255,255,0.85);
}

/* Order cards list */
.dispute-orders-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 220px;
    overflow-y: auto;
    padding-right: 8px;
    padding-bottom: 4px;
}
.dispute-orders-list::-webkit-scrollbar { width: 4px; }
.dispute-orders-list::-webkit-scrollbar-track { background: transparent; }
.dispute-orders-list::-webkit-scrollbar-thumb { background: rgba(110,110,210,0.3); border-radius: 2px; }

.dispute-order-card {
    padding: 0.55rem 0.75rem;
    border: 1px solid rgba(110,110,210,0.2);
    border-radius: 4px;
    cursor: pointer;
    background: rgba(255,255,255,0.03);
    transition: border-color 0.15s, background 0.15s;
    flex-shrink: 0;
}
.dispute-order-card:hover { background: rgba(110,110,210,0.05); border-color: rgba(110,110,210,0.4); }
.dispute-order-card--active { border-color: rgba(110,110,210,0.65); background: rgba(110,110,210,0.09); }

.dispute-order-card__top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}
.dispute-order-card__num   { font-weight: 700; color: rgba(255,255,255,0.45); font-size: 0.78rem; flex-shrink: 0; }
.dispute-order-card__idol  { flex: 1; font-size: 0.84rem; color: rgba(255,255,255,0.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dispute-order-card__total { font-size: 0.78rem; font-weight: 600; color: rgba(255,255,255,0.6); white-space: nowrap; flex-shrink: 0; }
.dispute-order-card__timer { font-size: 0.7rem; color: rgba(251,191,36,0.85); white-space: nowrap; flex-shrink: 0; }

.dispute-order-card__dates {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.3);
}

/* Field errors */
.dispute-field-error {
    font-size: 0.72rem;
    color: rgba(255, 100, 100, 0.85);
    margin: -0.1rem 0 0.1rem;
}

.dispute-textarea {
    resize: vertical;
    min-height: 80px;
}

.dispute-charcount {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.3);
    text-align: right;
    margin-top: -0.25rem;
}

.dispute-charcount--warn {
    color: rgba(255,180,60,0.75);
}

.dispute-submit {
    margin-top: 0.5rem;
    padding: 0.55rem 1rem;
    background: rgba(110,110,210,0.1);
    border: 1px solid rgba(110,110,210,0.35);
    color: rgba(160,150,255,0.9);
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    border-radius: 3px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    font-family: inherit;
}

.dispute-submit:hover:not(:disabled) {
    background: rgba(110,110,210,0.17);
    border-color: rgba(110,110,210,0.6);
}

.dispute-submit:disabled {
    opacity: 0.4;
    cursor: default;
}
</style>
