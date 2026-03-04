<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    phase: String,   // 'none' | 'quiz' | 'photo' | 'pending' | 'approved' | 'rejected' | 'cooldown'
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
    else if (props.phase === 'quiz') step.value = 2;
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
        alert(e.response?.data?.error || 'Не удалось начать тест');
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
        <div class="apply-card">

            <!-- ─── Step 1: Memo ─────────────────────────────── -->
            <div v-if="step === 1" class="step-content">
                <div class="step-icon">🌟</div>
                <h1 class="step-title">Стань Айдолом</h1>
                <p class="step-sub">Айдол — это вдохновение для других. Прочитайте требования перед началом.</p>

                <div class="memo-block">
                    <div class="memo-item">
                        <span class="memo-icon">📸</span>
                        <div>
                            <strong>Реальное фото</strong>
                            <p>Ваше лицо должно быть хорошо видно на фотографии</p>
                        </div>
                    </div>
                    <div class="memo-item">
                        <span class="memo-icon">✅</span>
                        <div>
                            <strong>Тест из 10 вопросов</strong>
                            <p>Допускается не более 2 ошибок. Есть 2 попытки, потом кулдаун 24ч</p>
                        </div>
                    </div>
                    <div class="memo-item">
                        <span class="memo-icon">⏳</span>
                        <div>
                            <strong>Проверка администратором</strong>
                            <p>После подачи заявки наша команда проверит её в течение нескольких дней</p>
                        </div>
                    </div>
                    <div class="memo-item">
                        <span class="memo-icon">🎭</span>
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
            <div v-else-if="step === 2" class="step-content">
                <div class="quiz-header">
                    <span class="quiz-stage">Вопрос {{ quizCurrentStage }} / 10</span>
                    <span class="quiz-errors" :class="{ 'quiz-errors--warn': quizErrors > 0 }">
                        Ошибки: {{ quizErrors }}/2
                    </span>
                </div>

                <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
                </div>

                <div v-if="currentQuestion" class="question-block">
                    <p class="question-text">{{ currentQuestion.question }}</p>

                    <div class="options-grid">
                        <button
                            v-for="(opt, i) in currentQuestion.options"
                            :key="i"
                            class="option-card"
                            :class="{
                                'option-card--selected': selectedAnswer === i && !showAnswerFeedback,
                                'option-card--correct': showAnswerFeedback && i === lastCorrectIndex,
                                'option-card--wrong': showAnswerFeedback && selectedAnswer === i && !lastAnswerCorrect && i !== lastCorrectIndex,
                                'option-card--disabled': showAnswerFeedback && i !== lastCorrectIndex && i !== selectedAnswer,
                            }"
                            @click="submitAnswer(i)"
                            :disabled="quizSubmitting || showAnswerFeedback"
                        >
                            <span class="option-letter">{{ String.fromCharCode(65 + i) }}</span>
                            {{ opt }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ─── Step 3: Result ───────────────────────────── -->
            <div v-else-if="step === 3" class="step-content">
                <!-- Passed -->
                <template v-if="quizSessionStatus === 'passed' || phase === 'photo'">
                    <div class="step-icon">🎉</div>
                    <h2 class="step-title">Тест пройден!</h2>
                    <p class="step-sub">Отлично! Теперь загрузите своё фото для заявки.</p>
                    <button @click="step = 4" class="btn-primary">Загрузить фото</button>
                </template>

                <!-- Failed once -->
                <template v-else-if="quizSessionStatus === 'failed' && quizAttemptNumber < 2">
                    <div class="step-icon">😔</div>
                    <h2 class="step-title">Не прошли с первого раза</h2>
                    <p class="step-sub">Ошибок: {{ quizErrors }}. У вас есть второй шанс — без кулдауна.</p>
                    <button @click="startQuiz" class="btn-primary" :disabled="quizStarting">
                        {{ quizStarting ? 'Запуск...' : 'Вторая попытка' }}
                    </button>
                </template>

                <!-- Failed twice → cooldown -->
                <template v-else-if="(quizSessionStatus === 'failed' && quizAttemptNumber >= 2) || phase === 'cooldown'">
                    <div class="step-icon">⏳</div>
                    <h2 class="step-title">Кулдаун активен</h2>
                    <p class="step-sub">Вы использовали обе попытки. Попробуйте снова через:</p>
                    <div class="cooldown-timer">{{ cooldownRemaining || '...' }}</div>
                </template>
            </div>

            <!-- ─── Step 4: Photo ────────────────────────────── -->
            <div v-else-if="step === 4" class="step-content">
                <div class="step-icon">📸</div>
                <h2 class="step-title">Фото для заявки</h2>
                <p class="step-sub">Ваше лицо должно быть хорошо видно. Принимаются форматы JPEG, PNG, WebP (до 5 МБ).</p>

                <div
                    class="dropzone"
                    :class="{ 'dropzone--has-file': photoPreview }"
                    @dragover.prevent
                    @drop="onDrop"
                    @click="$refs.fileInput.click()"
                >
                    <img v-if="photoPreview" :src="photoPreview" class="photo-preview" alt="Preview" />
                    <template v-else>
                        <span class="dropzone-icon">⬆️</span>
                        <p class="dropzone-text">Перетащите фото или нажмите для выбора</p>
                    </template>
                </div>
                <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFileChange" />

                <p v-if="photoForm.errors.face_photo" class="error-msg">{{ photoForm.errors.face_photo }}</p>

                <button
                    @click="submitPhoto"
                    class="btn-primary"
                    :disabled="!photoPreview || photoForm.processing"
                >
                    {{ photoForm.processing ? 'Отправка...' : 'Подать заявку' }}
                </button>
            </div>

            <!-- ─── Step 5: Status screens ───────────────────── -->
            <div v-else-if="step === 5" class="step-content">
                <!-- Done / Pending -->
                <template v-if="phase === 'pending' || phase === 'none'">
                    <div class="step-icon">📬</div>
                    <h2 class="step-title">Заявка принята!</h2>
                    <p class="step-sub">Мы рассмотрим её в ближайшее время. Уведомление придёт на почту и в колокольчик.</p>
                </template>
                <!-- Approved -->
                <template v-else-if="phase === 'approved'">
                    <div class="step-icon">🌟</div>
                    <h2 class="step-title">Вы Айдол!</h2>
                    <p class="step-sub">Ваш статус подтверждён. Спасибо за то, что вдохновляете других!</p>
                </template>
                <!-- Rejected -->
                <template v-else-if="phase === 'rejected'">
                    <div class="step-icon">❌</div>
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
.apply-wrap {
    min-height: calc(100vh - 60px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.apply-card {
    width: 100%;
    max-width: 560px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(200,70,126,0.15);
    border-radius: 20px;
    padding: 2.5rem 2rem;
    box-shadow: 0 8px 40px rgba(0,0,0,0.4);
}

.step-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    text-align: center;
}

.step-icon { font-size: 3rem; line-height: 1; }
.step-title { font-size: 1.6rem; color: #fff; margin: 0; font-weight: 600; }
.step-sub { color: rgba(255,255,255,0.5); font-size: 0.9rem; margin: 0; line-height: 1.6; max-width: 400px; }

/* Memo */
.memo-block {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    text-align: left;
}
.memo-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    background: rgba(255,255,255,0.03);
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.05);
}
.memo-icon { font-size: 1.25rem; flex-shrink: 0; }
.memo-item strong { display: block; color: rgba(255,255,255,0.85); font-size: 0.9rem; margin-bottom: 0.2rem; }
.memo-item p { margin: 0; font-size: 0.8rem; color: rgba(255,255,255,0.4); line-height: 1.4; }

/* Buttons */
.btn-primary {
    padding: 0.75rem 2rem;
    background: linear-gradient(135deg, #C8467E, #a03466);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    min-width: 160px;
}
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

.btn-secondary {
    padding: 0.65rem 1.5rem;
    background: transparent;
    border: 1px solid rgba(200,70,126,0.4);
    border-radius: 10px;
    color: #C8467E;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-secondary:hover { background: rgba(200,70,126,0.08); }

/* Quiz */
.quiz-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.quiz-stage { font-size: 0.85rem; color: rgba(255,255,255,0.5); }
.quiz-errors { font-size: 0.82rem; color: rgba(255,255,255,0.35); }
.quiz-errors--warn { color: #fbb740; }

.progress-bar {
    width: 100%;
    height: 4px;
    background: rgba(255,255,255,0.08);
    border-radius: 99px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #C8467E, #e05590);
    border-radius: 99px;
    transition: width 0.4s ease;
}

.question-block { width: 100%; }
.question-text {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.9);
    text-align: left;
    margin-bottom: 1.25rem;
    line-height: 1.55;
}

.options-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}
@media (max-width: 480px) { .options-grid { grid-template-columns: 1fr; } }

.option-card {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.8rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    color: rgba(255,255,255,0.75);
    font-size: 0.88rem;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s;
    line-height: 1.4;
}
.option-card:hover:not(:disabled) {
    background: rgba(200,70,126,0.08);
    border-color: rgba(200,70,126,0.3);
    color: rgba(255,255,255,0.95);
}
.option-card--selected {
    background: rgba(200,70,126,0.1);
    border-color: rgba(200,70,126,0.5);
    color: #fff;
}
.option-card--correct {
    background: rgba(76,222,143,0.12) !important;
    border-color: rgba(76,222,143,0.5) !important;
    color: #4cde8f !important;
}
.option-card--wrong {
    background: rgba(255,80,80,0.1) !important;
    border-color: rgba(255,80,80,0.4) !important;
    color: #ff6b6b !important;
}
.option-card--disabled {
    opacity: 0.35;
    cursor: default;
}
.option-card:disabled { cursor: default; }

.option-letter {
    font-weight: 700;
    font-size: 0.8rem;
    min-width: 18px;
    color: inherit;
    opacity: 0.6;
}

/* Cooldown */
.cooldown-timer {
    font-size: 2rem;
    font-weight: 700;
    color: #C8467E;
    letter-spacing: 0.05em;
    font-variant-numeric: tabular-nums;
}

/* Photo */
.dropzone {
    width: 100%;
    min-height: 160px;
    border: 2px dashed rgba(200,70,126,0.3);
    border-radius: 12px;
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
.dropzone:hover { border-color: rgba(200,70,126,0.55); background: rgba(200,70,126,0.04); }
.dropzone--has-file { border-style: solid; border-color: rgba(200,70,126,0.4); }
.dropzone-icon { font-size: 2rem; }
.dropzone-text { font-size: 0.85rem; color: rgba(255,255,255,0.4); }
.photo-preview { max-width: 100%; max-height: 240px; border-radius: 8px; object-fit: cover; }

.error-msg { font-size: 0.82rem; color: #ff6b6b; margin: 0; }

/* Rejection reason */
.rejection-reason {
    background: rgba(255,80,80,0.06);
    border: 1px solid rgba(255,80,80,0.2);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    text-align: left;
    max-width: 400px;
    line-height: 1.5;
}
</style>
