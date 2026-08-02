<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { Camera, CircleCheck, Clock, InfoFilled, Trophy, Remove, Upload, Message, StarFilled, CircleClose } from '@element-plus/icons-vue';
import { useTranslations } from '@/composables/useTranslations';

defineOptions({ layout: AppLayout });

const { __ } = useTranslations();

const props = defineProps({
    phase: String,   // 'none' | 'quiz' | 'photo' | 'pending' | 'approved' | 'rejected' | 'cooldown'
    session: Object,
    cooldown_until: String,
    rejection_reason: String,
    quiz_passed: Boolean,
    article_html: String,
});

const defaultArticleHtml = `
<h2>Стань Айдолом</h2>
<p>Айдол — это вдохновение для других. Вы будете проводить трансляции, общаться, помогать людям справляться со скукой и одиночеством. Перед тем как подать заявку, пожалуйста, внимательно ознакомьтесь с нашими правилами и условиями.</p>

<h3>Основные требования:</h3>
<ul>
    <li><strong>Реальная фотография лица.</strong> Ваше лицо должно быть хорошо и четко видно на фотографии (без масок, сильных фильтров или темных очков). Мы заботимся о безопасности и честности на нашей платформе.</li>
    <li><strong>Прохождение тестирования.</strong> Вам нужно будет пройти тест из 10 вопросов на знание правил сообщества и базовую эмпатию. Допускается не более 2 ошибок. Дается 2 попытки, после чего включается ограничение на 24 часа.</li>
    <li><strong>Ответственность и уважение.</strong> Айдол обязан соблюдать правила платформы, уважать границы участников, быть вежливым и дружелюбным. Любое проявление агрессии или неуважения приведет к лишению статуса.</li>
    <li><strong>Проверка модератором.</strong> После прохождения теста и загрузки фото ваша заявка будет отправлена на проверку администрации. Обычно это занимает от 1 до 3 рабочих дней.</li>
</ul>
`;

// Wizard step: 1=memo, 2=quiz, 3=result, 4=photo, 5=done
const step = ref(1);

// Initialize step based on server phase
onMounted(() => {
    if (props.phase === 'photo') step.value = 4;
    else if (props.phase === 'pending') step.value = 5;
    else if (props.phase === 'approved') step.value = 5;
    else if (props.phase === 'rejected') step.value = 5;
    else if (props.phase === 'cooldown') step.value = 3;
    else if (props.phase === 'quiz') {
        step.value = 2;
        if (props.session) {
            quizSessionId.value = props.session.id;
            quizAttemptNumber.value = props.session.attempt_number;
            quizErrors.value = props.session.errors_count;
            quizQuestions.value = props.session.all_questions;
            quizCurrentStage.value = props.session.current_stage;
        }
    }
    // else step.value = 1 (default: memo)
});

// ── Quiz state ───────────────────────────────────────────────
const quizSessionId = ref(null);
const quizQuestions = ref([]); // all questions array
const quizCurrentStage = ref(1);
const quizAnswers = ref({}); // stage -> answer
const quizErrors = ref(0);
const quizSessionStatus = ref(null); // 'passed' | 'failed'
const quizAttemptNumber = ref(1);
const quizSubmitting = ref(false);
const selectedAnswer = ref(null);
const showAnswerFeedback = ref(false);
const lastAnswerCorrect = ref(null);
const lastCorrectIndex = ref(null);
const quizStarting = ref(false);

const currentQuestion = computed(() =>
    quizQuestions.value.find(q => q.stage === quizCurrentStage.value)
);

async function startQuiz() {
    quizStarting.value = true;
    try {
        const res = await axios.post(route('idol.quiz.start'));
        quizSessionId.value = res.data.session_id;
        quizAttemptNumber.value = res.data.attempt_number;
        quizQuestions.value = res.data.questions;
        quizCurrentStage.value = 1;
        quizErrors.value = 0;
        quizAnswers.value = {};
        quizSessionStatus.value = null;
        selectedAnswer.value = null;
        showAnswerFeedback.value = false;
        step.value = 2;
    } catch (e) {
        const data = e.response?.data;
        if (data?.cooldown_until) {
            quizSessionStatus.value = 'failed';
            quizAttemptNumber.value = 2;
            updateCooldown(data.cooldown_until);
            step.value = 3;
        } else {
            alert(data?.error || 'Не удалось начать тест');
        }
    } finally {
        quizStarting.value = false;
    }
}

async function submitAnswer(answerIdx) {
    if (quizSubmitting.value || showAnswerFeedback.value) return;
    selectedAnswer.value = answerIdx;
    quizSubmitting.value = true;

    try {
        const res = await axios.post(route('idol.quiz.answer'), {
            session_id: quizSessionId.value,
            stage: quizCurrentStage.value,
            answer_index: answerIdx,
        });

        lastAnswerCorrect.value = res.data.correct;
        lastCorrectIndex.value = res.data.correct_index;
        quizErrors.value = res.data.errors_count;
        showAnswerFeedback.value = true;

        if (res.data.session_status) {
            quizSessionStatus.value = res.data.session_status;
            if (res.data.cooldown_until) {
                updateCooldown(res.data.cooldown_until);
            }
        }

        setTimeout(() => {
            showAnswerFeedback.value = false;
            if (quizCurrentStage.value === 10 || res.data.session_status) {
                step.value = 3;
            } else {
                quizCurrentStage.value++;
                selectedAnswer.value = null;
            }
        }, 1200);
    } catch (e) {
        alert('Ошибка при отправке ответа');
    } finally {
        quizSubmitting.value = false;
    }
}

// ── Browser back button fix ────────────────────────────────────
function onPopState() {
    router.visit(window.location.href, { replace: true });
}
onMounted(() => window.addEventListener('popstate', onPopState));
onUnmounted(() => window.removeEventListener('popstate', onPopState));

// ── Cooldown timer ────────────────────────────────────────────
const cooldownRemaining = ref('');
let cooldownInterval;

function updateCooldown(until) {
    const end = new Date(until);
    function tick() {
        const diff = end - Date.now();
        if (diff <= 0) {
            cooldownRemaining.value = '';
            clearInterval(cooldownInterval);
            return;
        }
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        cooldownRemaining.value = `${h}ч ${m}м ${s}с`;
    }
    tick();
    cooldownInterval = setInterval(tick, 1000);
}

onMounted(() => {
    if (props.cooldown_until) updateCooldown(props.cooldown_until);
});
onUnmounted(() => { clearInterval(cooldownInterval); });

// ── Photo upload ──────────────────────────────────────────────
const photoForm = useForm({ face_photo: null });
const photoPreview = ref(null);

function onFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    photoForm.face_photo = file;
    const reader = new FileReader();
    reader.onload = (ev) => { photoPreview.value = ev.target.result; };
    reader.readAsDataURL(file);
}

function onDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file) return;
    photoForm.face_photo = file;
    const reader = new FileReader();
    reader.onload = (ev) => { photoPreview.value = ev.target.result; };
    reader.readAsDataURL(file);
}

function submitPhoto() {
    photoForm.post(route('idol.apply.store'), {
        onSuccess: () => { step.value = 5; },
    });
}

const progressPercent = computed(() => Math.round((quizCurrentStage.value / 10) * 100));
</script>

<template>
    <div class="apply-wrap">
        <div class="apply-container">
            <Link :href="route('profile')" class="back-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.35rem;">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                {{ __('idol.apply.back_to_profile') }}
            </Link>

            <!-- ─── Step 1: Article ─── -->
            <div v-if="step === 1" class="article-layout">
                <div class="article-warning">
                    <div class="warning-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </div>
                    <div class="warning-content">
                        <strong>{{ __("idol.apply.warning_title") }}</strong> {{ __("idol.apply.warning_text") }}
                    </div>
                </div>

                <div class="article-body" v-html="article_html || defaultArticleHtml"></div>

                <div class="article-actions">
                    <button @click="startQuiz" class="btn-start-quiz" :disabled="quizStarting">
                        {{ quizStarting ? 'Запуск...' : 'Начать тест' }}
                    </button>
                </div>
            </div>

            <!-- ─── Steps 2-5: Interactive Wizard Card (Glassmorphic panel for focus) ─── -->
            <div v-else class="wizard-card">
                <!-- ─── Step 2: Quiz ─── -->
                <div v-if="step === 2" class="quiz-wrap">
                    <!-- Top bar -->
                    <div class="quiz-topbar">
                        <div class="quiz-stage-label">
                            <span class="quiz-stage-num">{{ quizCurrentStage }}</span>
                            <span class="quiz-stage-sep">/</span>
                            <span class="quiz-stage-total">10</span>
                        </div>
                        <div class="quiz-errors-badge" :class="{
                            'quiz-errors-badge--zero': quizErrors === 0,
                            'quiz-errors-badge--one': quizErrors === 1,
                            'quiz-errors-badge--two': quizErrors >= 2,
                        }">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            {{ quizErrors }} / 2
                        </div>
                    </div>

                    <!-- Segmented progress -->
                    <div class="quiz-segments">
                        <div v-for="n in 10" :key="n" class="quiz-segment" :class="{
                            'quiz-segment--done': n < quizCurrentStage,
                            'quiz-segment--active': n === quizCurrentStage,
                        }"></div>
                    </div>

                    <!-- Warning -->
                    <div v-if="quizErrors >= 2" class="quiz-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                        Следующая ошибка завершит тест
                    </div>

                    <!-- Question -->
                    <div v-if="currentQuestion" class="question-block">
                        <p class="question-text">{{ currentQuestion.question }}</p>

                        <div class="options-list">
                            <button v-for="(opt, i) in currentQuestion.options" :key="i" class="option-row" :class="{
                                'option-row--selected': selectedAnswer === i && !showAnswerFeedback,
                                'option-row--correct': showAnswerFeedback && i === lastCorrectIndex,
                                'option-row--wrong': showAnswerFeedback && selectedAnswer === i && !lastAnswerCorrect && i !== lastCorrectIndex,
                                'option-row--muted': showAnswerFeedback && i !== lastCorrectIndex && i !== selectedAnswer,
                            }" @click="submitAnswer(i)" :disabled="quizSubmitting || showAnswerFeedback">
                                <span class="option-badge">{{ String.fromCharCode(65 + i) }}</span>
                                <span class="option-text">{{ opt }}</span>
                                <svg v-if="showAnswerFeedback && i === lastCorrectIndex"
                                    class="option-icon option-icon--correct" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <svg v-else-if="showAnswerFeedback && selectedAnswer === i && !lastAnswerCorrect"
                                    class="option-icon option-icon--wrong" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ─── Step 3: Result ─── -->
                <div v-else-if="step === 3" class="step-content">
                    <!-- Passed -->
                    <template v-if="quizSessionStatus === 'passed' || phase === 'photo'">
                        <el-icon class="step-icon">
                            <Trophy />
                        </el-icon>
                        <h2 class="step-title">Тест пройден!</h2>
                        <p class="step-sub">Отлично! Теперь загрузите своё фото для заявки.</p>
                        <button @click="step = 4" class="btn-primary">Загрузить фото</button>
                    </template>

                    <!-- Failed once -->
                    <template v-else-if="quizSessionStatus === 'failed' && quizAttemptNumber < 2">
                        <el-icon class="step-icon">
                            <Remove />
                        </el-icon>
                        <h2 class="step-title">Не прошли с первого раза</h2>
                        <p class="step-sub">Ошибок: {{ quizErrors }}. У вас есть второй шанс — без кулдауна.</p>
                        <button @click="startQuiz" class="btn-primary" :disabled="quizStarting">
                            {{ quizStarting ? 'Запуск...' : 'Вторая попытка' }}
                        </button>
                    </template>

                    <!-- Failed twice → cooldown -->
                    <template
                        v-else-if="(quizSessionStatus === 'failed' && quizAttemptNumber >= 2) || phase === 'cooldown'">
                        <el-icon class="step-icon">
                            <Clock />
                        </el-icon>
                        <h2 class="step-title">Кулдаун активен</h2>
                        <p class="step-sub">Вы использовали обе попытки. Попробуйте снова через:</p>
                        <div class="cooldown-timer">{{ cooldownRemaining || '...' }}</div>
                    </template>
                </div>

                <!-- ─── Step 4: Photo ─── -->
                <div v-else-if="step === 4" class="step-content">
                    <el-icon class="step-icon">
                        <Camera />
                    </el-icon>
                    <h2 class="step-title">Фото для заявки</h2>
                    <p class="step-sub">Ваше лицо должно быть хорошо видно. Принимаются форматы JPEG, PNG, WebP (до 5 МБ).</p>

                    <div class="dropzone" :class="{ 'dropzone--has-file': photoPreview }" @dragover.prevent @drop="onDrop"
                        @click="$refs.fileInput.click()">
                        <img v-if="photoPreview" :src="photoPreview" class="photo-preview" alt="Preview" />
                        <template v-else>
                            <el-icon class="dropzone-icon">
                                <Upload />
                            </el-icon>
                            <p class="dropzone-text">Перетащите фото или нажмите для выбора</p>
                        </template>
                    </div>
                    <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFileChange" />

                    <p v-if="photoForm.errors.face_photo" class="error-msg">{{ photoForm.errors.face_photo }}</p>

                    <button @click="submitPhoto" class="btn-primary" :disabled="!photoPreview || photoForm.processing">
                        {{ photoForm.processing ? 'Отправка...' : 'Подать заявку' }}
                    </button>
                </div>

                <!-- ─── Step 5: Status screens ─── -->
                <div v-else-if="step === 5" class="step-content">
                    <!-- Done / Pending -->
                    <template v-if="phase === 'pending' || phase === 'none'">
                        <el-icon class="step-icon">
                            <Message />
                        </el-icon>
                        <h2 class="step-title">Заявка принята!</h2>
                        <p class="step-sub">Мы рассмотрим её в ближайшее время. Уведомление придёт на почту и в колокольчик.</p>
                    </template>
                    <!-- Approved -->
                    <template v-else-if="phase === 'approved'">
                        <el-icon class="step-icon">
                            <StarFilled />
                        </el-icon>
                        <h2 class="step-title">Вы Айдол!</h2>
                        <p class="step-sub">Ваш статус подтверждён. Спасибо за то, что вдохновляете других!</p>
                    </template>
                    <!-- Rejected -->
                    <template v-else-if="phase === 'rejected'">
                        <el-icon class="step-icon">
                            <CircleClose />
                        </el-icon>
                        <h2 class="step-title">Заявка отклонена</h2>
                        <p class="step-sub">К сожалению, ваша заявка была отклонена.</p>
                        <div v-if="rejection_reason" class="rejection-reason">
                            <strong>Причина:</strong> {{ rejection_reason }}
                        </div>
                        <button @click="step = quiz_passed ? 4 : 1" class="btn-secondary">Попробовать снова</button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════
   Layout — full-width page, article reading column
   ═══════════════════════════════════════════════════════ */
.apply-wrap {
    min-height: calc(100vh - 60px);
    width: 100%;
    font-family: 'Rubik', sans-serif;
    box-sizing: border-box;
}

/* Outer padding shell — generous breathing room */
.apply-container {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    padding: 3.5rem 2.5rem 6rem;
    display: flex;
    flex-direction: column;
    gap: 2rem;
    box-sizing: border-box;
}

/* ── Back button ──────────────────────────────────────── */
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    align-self: flex-start;
    color: rgba(255, 255, 255, 0.38);
    font-size: 0.82rem;
    font-weight: 500;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: color 0.18s;
    text-decoration: none;
}

.back-btn:hover {
    color: #ffb2ef;
}

/* ═══════════════════════════════════════════════════════
   Article Layout — full width with centered text column
   ═══════════════════════════════════════════════════════ */
.article-layout {
    display: flex;
    flex-direction: column;
    width: 100%;
    animation: fadeIn 0.5s ease-out both;
}

/* Eyebrow label above the title */
.step-eyebrow {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #ffb2ef;
    margin-bottom: 0.6rem;
}

/* Article body — constrained reading width, centered */
.article-body {
    width: 100%;
    max-width: 950px;       /* optimal reading width */
    margin: 0 auto;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.85;
    font-size: 1.07rem;
}

/* ── Article Typography ───────────────────────────────── */
.article-body :deep(h1) {
    font-size: 2.6rem;
    font-weight: 700;
    margin: 0 0 1rem;
    line-height: 1.15;
    color: rgba(255, 255, 255, 0.88);
}

.article-body :deep(h2) {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 2.75rem 0 1rem;
    line-height: 1.2;
    color: rgba(255, 255, 255, 0.82);
}

/* First heading gets no top margin */
.article-body :deep(h2:first-child),
.article-body :deep(h1:first-child) {
    margin-top: 0;
}

.article-body :deep(h3) {
    font-size: 1.15rem;
    font-weight: 600;
    color: rgba(255, 178, 239, 0.8);
    margin: 2rem 0 0.65rem;
    letter-spacing: -0.01em;
}

.article-body :deep(h4) {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.65);
    margin: 1.5rem 0 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    font-size: 0.75rem;
}

.article-body :deep(p) {
    margin: 0 0 1.3rem;
    color: rgba(255, 255, 255, 0.52);
}

/* Lead paragraph — first p slightly larger */
.article-body :deep(p:first-of-type) {
    font-size: 1.08rem;
    color: rgba(255, 255, 255, 0.58);
    line-height: 1.75;
}

.article-body :deep(ul),
.article-body :deep(ol) {
    margin: 0 0 1.75rem;
    padding-left: 0;
    list-style: none;
}

.article-body :deep(ol) {
    counter-reset: ol-counter;
}

.article-body :deep(li) {
    position: relative;
    padding-left: 1.75rem;
    margin-bottom: 0.9rem;
    color: rgba(255, 255, 255, 0.52);
    line-height: 1.7;
}

/* Bullet */
.article-body :deep(ul li::before) {
    content: "";
    position: absolute;
    left: 0.3rem;
    top: 0.6em;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ffb2ef;
    box-shadow: 0 0 6px rgba(255, 178, 239, 0.5);
}

/* Numbered list counter */
.article-body :deep(ol li) {
    counter-increment: ol-counter;
}

.article-body :deep(ol li::before) {
    content: counter(ol-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 1.25rem;
    text-align: right;
    font-size: 0.8rem;
    font-weight: 700;
    color: #ffb2ef;
    line-height: 1.85;
}

.article-body :deep(li strong) {
    color: rgba(255, 255, 255, 0.82);
    font-weight: 600;
}

/* Blockquote — styled callout */
.article-body :deep(blockquote) {
    margin: 2rem 0;
    padding: 1.25rem 1.5rem;
    border-left: 2px solid rgba(255, 178, 239, 0.45);
    background: rgba(255, 178, 239, 0.03);
    border-radius: 0 6px 6px 0;
    color: rgba(255, 255, 255, 0.45);
    font-style: italic;
}

/* Horizontal rule */
.article-body :deep(hr) {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin: 2.5rem 0;
}

/* Inline code */
.article-body :deep(code) {
    font-size: 0.88em;
    background: rgba(255, 178, 239, 0.08);
    border: 1px solid rgba(255, 178, 239, 0.18);
    border-radius: 4px;
    padding: 0.1em 0.4em;
    color: #ffb2ef;
    font-family: 'JetBrains Mono', monospace;
}

/* Bold / em */
.article-body :deep(strong) {
    color: rgba(255, 255, 255, 0.82);
    font-weight: 600;
}

.article-body :deep(em) {
    color: rgba(255, 255, 255, 0.48);
}

/* ── Divider between article and CTA ─────────────────── */
.article-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 3rem;
    padding-top: 2.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
    gap: 1.5rem;
}

/* ── "Начать тест" — minimal CTA ─────────────────────── */
.btn-start-quiz {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.9rem 2.8rem;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    cursor: pointer;
    font-family: inherit;
    border-radius: 9px;

    background: linear-gradient(135deg, rgba(255, 178, 239, 0.12) 0%, rgba(255, 178, 239, 0.04) 100%);
    border: 1px solid rgba(255, 178, 239, 0.28);
    color: #ffffff;
    box-shadow: 
        0 2px 12px 0 rgba(255, 178, 239, 0.05),
        inset 0 1px 0 0 rgba(255, 255, 255, 0.35);
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);

    transition: all 0.2s ease-in-out;
}

.btn-start-quiz:hover {
    border-color: rgba(255, 178, 239, 0.45);
    background: linear-gradient(135deg, rgba(255, 178, 239, 0.18) 0%, rgba(255, 178, 239, 0.06) 100%);
    color: #ffffff;
    box-shadow: 
        0 4px 16px 0 rgba(255, 178, 239, 0.1),
        inset 0 1px 0 0 rgba(255, 255, 255, 0.4);
}

.btn-start-quiz:active {
    box-shadow: 
        0 1px 6px 0 rgba(255, 178, 239, 0.04),
        inset 0 1px 0 0 rgba(255, 255, 255, 0.18);
}

.btn-start-quiz:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Small hint text next to button */
.cta-hint {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.3);
    line-height: 1.5;
    max-width: 240px;
}

/* ═══════════════════════════════════════════════════════
   Wizard Card (Steps 2-5 — quiz, result, photo, status)
   ═══════════════════════════════════════════════════════ */
.wizard-card {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    background: rgba(10, 10, 15, 0.55);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 178, 239, 0.12);
    border-radius: 14px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
    overflow: hidden;
    animation: scaleIn 0.3s ease-out both;
}

.step-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    text-align: center;
    padding: 3.5rem 2.5rem;
}

.step-icon {
    font-size: 3rem;
    display: flex;
    justify-content: center;
    color: #ffb2ef;
}

.step-title {
    font-size: 1.6rem;
    color: #fff;
    margin: 0;
    font-weight: 700;
}

.step-sub {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.92rem;
    margin: 0;
    line-height: 1.6;
    max-width: 420px;
}

/* ── Buttons (wizard) ─────────────────────────────────── */
.btn-primary {
    padding: 0.8rem 2.2rem;
    background: #ffb2ef;
    border: 1px solid transparent;
    border-radius: 8px;
    color: #0b0b12;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 160px;
    box-shadow: 0 4px 16px rgba(255, 178, 239, 0.35);
}

.btn-primary:hover {
    background: #ffffff;
    box-shadow: 0 6px 20px rgba(255, 255, 255, 0.45);
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    padding: 0.7rem 1.6rem;
    background: transparent;
    border: 1px solid rgba(255, 178, 239, 0.4);
    border-radius: 8px;
    color: #ffb2ef;
    font-size: 0.88rem;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: rgba(255, 178, 239, 0.08);
    border-color: rgba(255, 178, 239, 0.7);
    color: #fff;
}

/* ── Quiz wrapper ─────────────────────────────────────── */
.quiz-wrap {
    display: flex;
    flex-direction: column;
}

/* ── Top bar ──────────────────────────────────────────── */
.quiz-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.quiz-stage-label {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.quiz-stage-num {
    font-size: 1.6rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.quiz-stage-sep {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.2);
}

.quiz-stage-total {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.4);
    font-variant-numeric: tabular-nums;
}

.quiz-errors-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    border: 1px solid;
    transition: all 0.2s;
}

.quiz-errors-badge svg {
    width: 0.9rem;
    height: 0.9rem;
}

.quiz-errors-badge--zero {
    color: rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
}

.quiz-errors-badge--one {
    color: #fbb740;
    background: rgba(251, 183, 64, 0.08);
    border-color: rgba(251, 183, 64, 0.28);
}

.quiz-errors-badge--two {
    color: #ff6b6b;
    background: rgba(255, 80, 80, 0.08);
    border-color: rgba(255, 80, 80, 0.35);
}

/* ── Segmented progress ───────────────────────────────── */
.quiz-segments {
    display: flex;
    gap: 4px;
    padding: 0.9rem 1.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.quiz-segment {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.08);
    transition: background 0.25s ease;
}

.quiz-segment--done {
    background: rgba(255, 178, 239, 0.45);
}

.quiz-segment--active {
    background: #ffb2ef;
    box-shadow: 0 0 8px rgba(255, 178, 239, 0.5);
}

/* ── Warning ──────────────────────────────────────────── */
.quiz-warning {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.75rem;
    background: rgba(255, 80, 80, 0.06);
    border-bottom: 1px solid rgba(255, 80, 80, 0.18);
    font-size: 0.82rem;
    font-weight: 600;
    color: #ff6b6b;
}

.quiz-warning svg {
    width: 0.95rem;
    height: 0.95rem;
}

/* ── Question ─────────────────────────────────────────── */
.question-block {
    padding: 2rem 1.75rem 2.25rem;
}

.question-text {
    font-size: 1.05rem;
    color: rgba(255, 255, 255, 0.95);
    margin: 0 0 1.5rem;
    line-height: 1.6;
    font-weight: 500;
}

/* ── Options ──────────────────────────────────────────── */
.options-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.option-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 7px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.92rem;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.45;
}

.option-row:hover:not(:disabled) {
    background: rgba(255, 178, 239, 0.06);
    border-color: rgba(255, 178, 239, 0.25);
    color: rgba(255, 255, 255, 0.95);
}

.option-row:disabled {
    cursor: default;
}

.option-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: rgba(255, 255, 255, 0.45);
    transition: all 0.15s;
}

.option-row--selected .option-badge {
    background: rgba(255, 178, 239, 0.2);
    border-color: rgba(255, 178, 239, 0.5);
    color: #ffb2ef;
}

.option-row--correct .option-badge {
    background: rgba(74, 222, 128, 0.15);
    border-color: rgba(74, 222, 128, 0.4);
    color: #4ade80;
}

.option-row--wrong .option-badge {
    background: rgba(255, 80, 80, 0.12);
    border-color: rgba(255, 80, 80, 0.35);
    color: #ff6b6b;
}

.option-row--selected {
    background: rgba(255, 178, 239, 0.08) !important;
    border-color: rgba(255, 178, 239, 0.4) !important;
    color: #fff !important;
}

.option-row--correct {
    background: rgba(74, 222, 128, 0.08) !important;
    border-color: rgba(74, 222, 128, 0.4) !important;
    color: #4ade80 !important;
}

.option-row--wrong {
    background: rgba(255, 80, 80, 0.08) !important;
    border-color: rgba(255, 80, 80, 0.35) !important;
    color: #ff6b6b !important;
}

.option-row--muted {
    opacity: 0.28;
}

.option-text {
    flex: 1;
}

.option-icon {
    flex-shrink: 0;
    width: 1rem;
    height: 1rem;
    margin-left: auto;
}

.option-icon--correct { color: #4ade80; }
.option-icon--wrong   { color: #ff6b6b; }

/* ── Cooldown ─────────────────────────────────────────── */
.cooldown-timer {
    font-size: 2.2rem;
    font-weight: 700;
    color: #ffb2ef;
    letter-spacing: 0.06em;
    font-variant-numeric: tabular-nums;
    text-shadow: 0 0 16px rgba(255, 178, 239, 0.35);
}

/* ── Photo dropzone ───────────────────────────────────── */
.dropzone {
    width: 100%;
    min-height: 160px;
    border: 1px dashed rgba(255, 178, 239, 0.3);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    cursor: pointer;
    transition: all 0.2s;
    padding: 1rem;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.01);
}

.dropzone:hover {
    border-color: rgba(255, 178, 239, 0.6);
    background: rgba(255, 178, 239, 0.04);
}

.dropzone--has-file {
    border-style: solid;
    border-color: rgba(255, 178, 239, 0.4);
}

.dropzone-icon { font-size: 2rem; }

.dropzone-text {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.35);
}

.photo-preview {
    max-width: 100%;
    max-height: 260px;
    border-radius: 4px;
    object-fit: cover;
}

.error-msg {
    font-size: 0.82rem;
    color: #ff6b6b;
    margin: 0;
}

/* ── Rejection reason ─────────────────────────────────── */
.rejection-reason {
    background: rgba(255, 80, 80, 0.05);
    border: 1px solid rgba(255, 80, 80, 0.18);
    border-radius: 6px;
    padding: 0.85rem 1.1rem;
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.65);
    text-align: left;
    max-width: 400px;
    line-height: 1.55;
}

/* ── Warning Alert Box ───────────────────────────────── */
.article-warning {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    width: 100%;
    max-width: 950px;
    margin: 0 auto 2rem;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(245, 158, 11, 0.04) 100%);
    border: 1px solid rgba(245, 158, 11, 0.28);
    border-radius: 12px;
    color: #fbd38d;
    box-sizing: border-box;
    font-size: 0.95rem;
    line-height: 1.6;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 
        0 8px 32px 0 rgba(0, 0, 0, 0.24), 
        inset 0 1px 0 0 rgba(255, 255, 255, 0.05);
}

.warning-icon {
    flex-shrink: 0;
    color: #f6ad55;
    margin-top: 0.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.warning-content strong {
    color: #f6ad55;
    font-weight: 600;
    margin-right: 0.25rem;
}

/* ── Animations ───────────────────────────────────────── */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.97); }
    to   { opacity: 1; transform: scale(1); }
}

/* ═══════════════════════════════════════════════════════
   Responsive
   ═══════════════════════════════════════════════════════ */
@media (max-width: 900px) {
    .apply-container {
        padding: 2.5rem 2rem 4rem;
    }
}

@media (max-width: 640px) {
    .apply-container {
        padding: 1.75rem 1.25rem 3.5rem;
    }

    .article-body {
        font-size: 1rem;
        line-height: 1.75;
    }

    .article-body :deep(h2) {
        font-size: 1.7rem;
    }

    .article-body :deep(h3) {
        font-size: 1.1rem;
    }

    .article-actions {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .btn-start-quiz {
        width: 100%;
        justify-content: center;
    }

    .cta-hint {
        max-width: 100%;
    }

    .step-content {
        padding: 2.5rem 1.25rem;
    }

    .wizard-card {
        border-radius: 10px;
    }
}
</style>
