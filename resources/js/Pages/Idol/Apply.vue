<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { Camera, CircleCheck, Clock, InfoFilled, Trophy, Remove, Upload, Message, StarFilled, CircleClose } from '@element-plus/icons-vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    phase: String,   // 'none' | 'quiz' | 'photo' | 'pending' | 'approved' | 'rejected' | 'cooldown'
    session: Object,
    cooldown_until: String,
    rejection_reason: String,
    quiz_passed: Boolean,
});

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
        <button class="back-btn" @click="history.back()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6" />
            </svg>
            Назад
        </button>
        <div class="apply-card">

            <!-- ─── Step 1: Memo ─────────────────────────────── -->
            <div v-if="step === 1" class="step-content">
                <div class="step-eyebrow">ЗАЯВКА НА СТАТУС</div>
                <h1 class="step-title">Стань Айдолом</h1>
                <p class="step-sub">Айдол — это вдохновение для других. Прочитайте требования перед началом.</p>

                <div class="memo-block">
                    <div class="memo-item">
                        <el-icon class="memo-icon">
                            <Camera />
                        </el-icon>
                        <div>
                            <strong>Реальное фото</strong>
                            <p>Ваше лицо должно быть хорошо видно на фотографии</p>
                        </div>
                    </div>
                    <div class="memo-item">
                        <el-icon class="memo-icon">
                            <CircleCheck />
                        </el-icon>
                        <div>
                            <strong>Тест из 10 вопросов</strong>
                            <p>Допускается не более 2 ошибок. Есть 2 попытки, потом кулдаун 24ч</p>
                        </div>
                    </div>
                    <div class="memo-item">
                        <el-icon class="memo-icon">
                            <Clock />
                        </el-icon>
                        <div>
                            <strong>Проверка администратором</strong>
                            <p>После подачи заявки наша команда проверит её в течение нескольких дней</p>
                        </div>
                    </div>
                    <div class="memo-item">
                        <el-icon class="memo-icon">
                            <InfoFilled />
                        </el-icon>
                        <div>
                            <strong>Ответственность</strong>
                            <p>Айдол должен соблюдать правила платформы и уважать участников</p>
                        </div>
                    </div>
                </div>

                <button @click="startQuiz" class="btn-primary" :disabled="quizStarting">
                    {{ quizStarting ? 'Запуск...' : 'Начать тест' }}
                </button>
            </div>

            <!-- ─── Step 2: Quiz ─────────────────────────────── -->
            <div v-else-if="step === 2" class="quiz-wrap">

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

            <!-- ─── Step 3: Result ───────────────────────────── -->
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

            <!-- ─── Step 4: Photo ────────────────────────────── -->
            <div v-else-if="step === 4" class="step-content">
                <el-icon class="step-icon">
                    <Camera />
                </el-icon>
                <h2 class="step-title">Фото для заявки</h2>
                <p class="step-sub">Ваше лицо должно быть хорошо видно. Принимаются форматы JPEG, PNG, WebP (до 5 МБ).
                </p>

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

            <!-- ─── Step 5: Status screens ───────────────────── -->
            <div v-else-if="step === 5" class="step-content">
                <!-- Done / Pending -->
                <template v-if="phase === 'pending' || phase === 'none'">
                    <el-icon class="step-icon">
                        <Message />
                    </el-icon>
                    <h2 class="step-title">Заявка принята!</h2>
                    <p class="step-sub">Мы рассмотрим её в ближайшее время. Уведомление придёт на почту и в колокольчик.
                    </p>
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
</template>

<style scoped>
/* ── Layout ───────────────────────────────────────────── */
.apply-wrap {
    min-height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    font-family: 'Rubik', sans-serif;
    gap: 1rem;
}

/* ── Back button ──────────────────────────────────────── */
.back-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.38);
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    padding: 0.25rem 0;
    align-self: flex-start;
    margin-left: calc((100% - 560px) / 2);
    transition: color 0.15s;
}

.back-btn:hover {
    color: rgba(255, 255, 255, 0.75);
}

@media (max-width: 600px) {
    .back-btn {
        margin-left: 0;
    }
}

.apply-card {
    width: 100%;
    max-width: 560px;
    background: #0a0a0f;
    border: 1px solid rgba(160, 160, 255, 0.18);
    border-radius: 4px;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.6), 0 16px 48px rgba(0, 0, 0, 0.6);
}

/* ── Generic step wrapper ─────────────────────────────── */
.step-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    text-align: center;
    padding: 2.5rem 2rem;
}

.step-eyebrow {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #a0a0ff;
}

.step-icon {
    font-size: 2.75rem;
    display: flex;
    justify-content: center;
}

.step-title {
    font-size: 1.55rem;
    color: #fff;
    margin: 0;
    font-weight: 700;
}

.step-sub {
    color: rgba(255, 255, 255, 0.45);
    font-size: 0.88rem;
    margin: 0;
    line-height: 1.65;
    max-width: 400px;
}

/* ── Memo ─────────────────────────────────────────────── */
.memo-block {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    text-align: left;
}

.memo-item {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    padding: 0.75rem 0.85rem;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 3px;
}

.memo-icon {
    font-size: 1.1rem;
    flex-shrink: 0;
    margin-top: 0.05rem;
}

.memo-item strong {
    display: block;
    color: rgba(255, 255, 255, 0.88);
    font-size: 0.85rem;
    margin-bottom: 0.15rem;
    font-weight: 600;
}

.memo-item p {
    margin: 0;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.38);
    line-height: 1.45;
}

/* ── Buttons ──────────────────────────────────────────── */
.btn-primary {
    padding: 0.7rem 2rem;
    background: #a0a0ff;
    border: none;
    border-radius: 3px;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    font-family: inherit;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    min-width: 160px;
}

.btn-primary:hover {
    opacity: 0.88;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    padding: 0.6rem 1.4rem;
    background: transparent;
    border: 1px solid rgba(160, 160, 255, 0.4);
    border-radius: 3px;
    color: #a0a0ff;
    font-size: 0.85rem;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}

.btn-secondary:hover {
    background: rgba(160, 160, 255, 0.07);
    border-color: rgba(160, 160, 255, 0.65);
}

/* ── Quiz wrapper ─────────────────────────────────────── */
.quiz-wrap {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Top bar ──────────────────────────────────────────── */
.quiz-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem 0.85rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.quiz-stage-label {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
}

.quiz-stage-num {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.quiz-stage-sep {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.2);
    margin: 0 0.05rem;
}

.quiz-stage-total {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.35);
    font-variant-numeric: tabular-nums;
}

.quiz-errors-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 3px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    border: 1px solid;
    transition: color 0.2s, background 0.2s, border-color 0.2s;
}

.quiz-errors-badge svg {
    width: 0.85rem;
    height: 0.85rem;
    flex-shrink: 0;
}

.quiz-errors-badge--zero {
    color: rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.07);
}

.quiz-errors-badge--one {
    color: #fbb740;
    background: rgba(251, 183, 64, 0.07);
    border-color: rgba(251, 183, 64, 0.25);
}

.quiz-errors-badge--two {
    color: #ff6b6b;
    background: rgba(255, 80, 80, 0.08);
    border-color: rgba(255, 80, 80, 0.3);
}

/* ── Segmented progress ───────────────────────────────── */
.quiz-segments {
    display: flex;
    gap: 3px;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.quiz-segment {
    flex: 1;
    height: 3px;
    border-radius: 0;
    background: rgba(255, 255, 255, 0.08);
    transition: background 0.25s ease;
}

.quiz-segment--done {
    background: rgba(160, 160, 255, 0.55);
}

.quiz-segment--active {
    background: #a0a0ff;
}

/* ── Warning ──────────────────────────────────────────── */
.quiz-warning {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.5rem;
    background: rgba(255, 80, 80, 0.06);
    border-bottom: 1px solid rgba(255, 80, 80, 0.18);
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: #ff6b6b;
}

.quiz-warning svg {
    width: 0.9rem;
    height: 0.9rem;
    flex-shrink: 0;
}

/* ── Question ─────────────────────────────────────────── */
.question-block {
    padding: 1.5rem 1.5rem 1.75rem;
}

.question-text {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.92);
    margin: 0 0 1.25rem;
    line-height: 1.6;
    font-weight: 500;
}

/* ── Options ──────────────────────────────────────────── */
.options-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.option-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.75rem 0.85rem;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 3px;
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.88rem;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s, color 0.12s;
    line-height: 1.45;
}

.option-row:hover:not(:disabled) {
    background: rgba(160, 160, 255, 0.07);
    border-color: rgba(160, 160, 255, 0.28);
    color: rgba(255, 255, 255, 0.92);
}

.option-row:disabled {
    cursor: default;
}

.option-row--selected {
    background: rgba(160, 160, 255, 0.1) !important;
    border-color: rgba(160, 160, 255, 0.5) !important;
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

.option-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: rgba(255, 255, 255, 0.4);
    transition: background 0.12s, border-color 0.12s, color 0.12s;
}

.option-row--selected .option-badge {
    background: rgba(160, 160, 255, 0.2);
    border-color: rgba(160, 160, 255, 0.5);
    color: #a0a0ff;
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

.option-text {
    flex: 1;
}

.option-icon {
    flex-shrink: 0;
    width: 1rem;
    height: 1rem;
    margin-left: auto;
}

.option-icon--correct {
    color: #4ade80;
}

.option-icon--wrong {
    color: #ff6b6b;
}

/* ── Cooldown ─────────────────────────────────────────── */
.cooldown-timer {
    font-size: 2rem;
    font-weight: 700;
    color: #a0a0ff;
    letter-spacing: 0.06em;
    font-variant-numeric: tabular-nums;
}

/* ── Photo dropzone ───────────────────────────────────── */
.dropzone {
    width: 100%;
    min-height: 150px;
    border: 1px dashed rgba(160, 160, 255, 0.3);
    border-radius: 3px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    padding: 1rem;
    box-sizing: border-box;
}

.dropzone:hover {
    border-color: rgba(160, 160, 255, 0.55);
    background: rgba(160, 160, 255, 0.04);
}

.dropzone--has-file {
    border-style: solid;
    border-color: rgba(160, 160, 255, 0.4);
}

.dropzone-icon {
    font-size: 1.75rem;
}

.dropzone-text {
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.35);
}

.photo-preview {
    max-width: 100%;
    max-height: 240px;
    border-radius: 2px;
    object-fit: cover;
}

.error-msg {
    font-size: 0.8rem;
    color: #ff6b6b;
    margin: 0;
}

/* ── Rejection reason ─────────────────────────────────── */
.rejection-reason {
    background: rgba(255, 80, 80, 0.05);
    border: 1px solid rgba(255, 80, 80, 0.18);
    border-radius: 3px;
    padding: 0.75rem 1rem;
    font-size: 0.84rem;
    color: rgba(255, 255, 255, 0.55);
    text-align: left;
    max-width: 400px;
    line-height: 1.55;
}
</style>
