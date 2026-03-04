<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    broadcasts: Array,
});

const form = useForm({
    title: '',
    body: '',
    target: 'all',
    target_user_id: '',
});

function submit() {
    form.post(route('admin.messages.store'), {
        onSuccess: () => {
            form.reset();
        },
    });
}

const targetLabel = { all: 'Все пользователи', user: 'Конкретный пользователь' };
</script>

<template>
    <div>
        <h1 class="page-title">Рассылки платформы</h1>

        <!-- Create form -->
        <div class="compose-card">
            <h2 class="compose-title">Новая рассылка</h2>
            <form @submit.prevent="submit" class="compose-form">
                <div class="field">
                    <label class="field-label">Заголовок</label>
                    <input v-model="form.title" class="field-input" placeholder="Заголовок сообщения" required />
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>
                <div class="field">
                    <label class="field-label">Сообщение</label>
                    <textarea v-model="form.body" class="field-textarea" rows="5" placeholder="Текст сообщения..." required />
                    <p v-if="form.errors.body" class="field-error">{{ form.errors.body }}</p>
                </div>
                <div class="field">
                    <label class="field-label">Получатели</label>
                    <select v-model="form.target" class="field-input">
                        <option value="all">Все пользователи</option>
                        <option value="user">Конкретный пользователь</option>
                    </select>
                </div>
                <div class="field" v-if="form.target === 'user'">
                    <label class="field-label">ID пользователя</label>
                    <input v-model="form.target_user_id" class="field-input" type="number" placeholder="Введите ID пользователя" />
                    <p v-if="form.errors.target_user_id" class="field-error">{{ form.errors.target_user_id }}</p>
                </div>
                <button type="submit" class="btn-send" :disabled="form.processing">
                    {{ form.processing ? 'Отправка...' : 'Отправить рассылку' }}
                </button>
            </form>
        </div>

        <!-- Sent list -->
        <div class="sent-section">
            <h2 class="sent-title">Отправленные рассылки</h2>
            <div v-if="broadcasts.length === 0" class="sent-empty">Рассылок пока нет</div>
            <div v-else class="sent-list">
                <div v-for="b in broadcasts" :key="b.id" class="sent-item">
                    <div class="sent-meta">
                        <span class="sent-date">{{ new Date(b.created_at).toLocaleString('ru') }}</span>
                        <span class="sent-target" :class="b.target === 'all' ? 'target--all' : 'target--user'">
                            {{ b.target === 'all' ? 'Все' : (b.target_user ? b.target_user.name + ' (' + b.target_user.email + ')' : 'Пользователь #' + b.target_user_id) }}
                        </span>
                        <span class="sent-by">{{ b.admin.name }}</span>
                    </div>
                    <p class="sent-headline">{{ b.title }}</p>
                    <p class="sent-body">{{ b.body }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

.compose-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(200,70,126,0.2);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    max-width: 600px;
}
.compose-title { font-size: 1rem; color: rgba(255,255,255,0.8); margin: 0 0 1.25rem; }

.compose-form { display: flex; flex-direction: column; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field-label { font-size: 0.8rem; color: rgba(255,255,255,0.45); }
.field-input, .field-textarea {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #fff;
    padding: 0.6rem 0.8rem; font-size: 0.9rem;
    outline: none; width: 100%; box-sizing: border-box;
}
.field-input:focus, .field-textarea:focus { border-color: rgba(200,70,126,0.5); }
.field-textarea { resize: vertical; font-family: inherit; }
.field-error { font-size: 0.78rem; color: #ff6b6b; margin: 0; }

.btn-send {
    align-self: flex-start;
    padding: 0.55rem 1.25rem; border-radius: 8px;
    background: rgba(200,70,126,0.15); border: 1px solid rgba(200,70,126,0.4);
    color: #C8467E; font-size: 0.9rem; cursor: pointer; transition: all 0.15s;
}
.btn-send:hover { background: rgba(200,70,126,0.25); }
.btn-send:disabled { opacity: 0.5; }

.sent-section { max-width: 700px; }
.sent-title { font-size: 1rem; color: rgba(255,255,255,0.6); margin: 0 0 1rem; }
.sent-empty { color: rgba(255,255,255,0.3); font-size: 0.85rem; }

.sent-list { display: flex; flex-direction: column; gap: 0.75rem; }
.sent-item {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 8px; padding: 1rem;
}
.sent-meta { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.sent-date { font-size: 0.75rem; color: rgba(255,255,255,0.3); }
.sent-target {
    font-size: 0.75rem; padding: 0.15rem 0.55rem; border-radius: 20px;
}
.target--all { background: rgba(76,222,143,0.1); color: #4cde8f; }
.target--user { background: rgba(200,70,126,0.1); color: #C8467E; }
.sent-by { font-size: 0.75rem; color: rgba(255,255,255,0.3); margin-left: auto; }
.sent-headline { font-size: 0.92rem; color: rgba(255,255,255,0.85); font-weight: 500; margin: 0 0 0.35rem; }
.sent-body { font-size: 0.82rem; color: rgba(255,255,255,0.5); margin: 0; white-space: pre-wrap; line-height: 1.5; }
</style>
