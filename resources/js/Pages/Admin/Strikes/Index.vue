<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UserPickerModal from '@/Components/Admin/UserPickerModal.vue';
import AppSelect from '@/Components/AppSelect.vue';

defineOptions({ layout: AdminLayout });

const form = useForm({
    user_id: '',
    rating_deducted: 0,
    admin_note: '',
    ban_reason: '',
    banned_until: '',
});

const showPicker = ref(false);
const selectedUser = ref(null);

const activeStrikesCount = computed(() => {
    return selectedUser.value ? (selectedUser.value.active_strikes_count || 0) : 0;
});

const isThirdStrike = computed(() => activeStrikesCount.value >= 2);

const ratingOptions = [
    { value: 0, label: 'Без вычета (0)' },
    { value: -0.1, label: '-0.1 (Легкое предупреждение)' },
    { value: -0.3, label: '-0.3 (Среднее нарушение)' },
    { value: -0.5, label: '-0.5 (Серьезное нарушение)' },
    { value: -1.0, label: '-1.0 (Грубое нарушение)' },
];

function selectUser(user) {
    // We need to fetch the actual active_strikes_count from the server because UserPickerModal 
    // might not have this specific attribute. But wait, we can just do an API call or reload 
    // the page with `q=user.id` to get the full user data with strikes_count from our StrikeController.
    selectedUser.value = user;
    form.user_id = user.id;
    
    // Fetch full info to get strikes count
    router.reload({
        data: { q: user.id },
        only: ['users'],
        onSuccess: (page) => {
            const found = page.props.users.find(u => u.id === user.id);
            if (found) {
                selectedUser.value = found;
            }
        }
    });
}

function submit() {
    form.post(route('admin.strikes.store'), {
        onSuccess: () => {
            form.reset();
            selectedUser.value = null;
        },
    });
}
</script>

<template>
    <div>
        <h1 class="page-title">Управление страйками</h1>

        <div class="compose-card">
            <h2 class="compose-title">Выдать страйк</h2>

            <form @submit.prevent="submit" class="compose-form">
                <div class="field">
                    <label class="field-label">Пользователь</label>
                    <div v-if="selectedUser" class="user-chip">
                        <span class="user-chip-avatar">{{ (selectedUser.name || selectedUser.email || '?')[0].toUpperCase() }}</span>
                        <span class="user-chip-info">
                            <span class="user-chip-name">
                                {{ selectedUser.name || '—' }} 
                                <span v-if="selectedUser.is_idol" class="badge-idol">Айдол</span>
                            </span>
                            <span class="user-chip-email">{{ selectedUser.email }}</span>
                        </span>
                        <div class="strikes-badge" :class="{'strikes-danger': activeStrikesCount > 0}">
                            Активных страйков: {{ activeStrikesCount }}
                        </div>
                        <button type="button" class="btn-change" @click="showPicker = true">Изменить</button>
                    </div>
                    <button v-else type="button" class="btn-pick" @click="showPicker = true">Выбрать пользователя</button>
                    <p v-if="form.errors.user_id" class="field-error">{{ form.errors.user_id }}</p>
                </div>

                <template v-if="selectedUser">
                    <div class="field" v-if="selectedUser.is_idol">
                        <label class="field-label">Списание рейтинга (только для айдолов)</label>
                        <AppSelect
                            v-model="form.rating_deducted"
                            :options="ratingOptions"
                        />
                        <p v-if="form.errors.rating_deducted" class="field-error">{{ form.errors.rating_deducted }}</p>
                    </div>

                    <div class="field">
                        <label class="field-label">Примечание от администрации (придет в письме)</label>
                        <textarea v-model="form.admin_note" class="field-textarea" rows="4" placeholder="Текст будет добавлен в письмо..."></textarea>
                        <p v-if="form.errors.admin_note" class="field-error">{{ form.errors.admin_note }}</p>
                    </div>

                    <div v-if="isThirdStrike" class="ban-section">
                        <div class="ban-alert">
                            ⚠️ Это 3-й страйк! Пользователь будет автоматически забанен. Пожалуйста, заполните данные бана.
                        </div>
                        <div class="field">
                            <label class="field-label">Причина бана</label>
                            <input v-model="form.ban_reason" type="text" class="field-input" placeholder="Например: Систематические нарушения правил" required />
                            <p v-if="form.errors.ban_reason" class="field-error">{{ form.errors.ban_reason }}</p>
                        </div>
                        <div class="field">
                            <label class="field-label">Забанить до (оставьте пустым для пермабана)</label>
                            <input v-model="form.banned_until" type="datetime-local" class="field-input" />
                            <p v-if="form.errors.banned_until" class="field-error">{{ form.errors.banned_until }}</p>
                        </div>
                    </div>

                    <button type="submit" class="btn-send" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение...' : (isThirdStrike ? 'Выдать страйк и ЗАБАНИТЬ' : 'Выдать страйк') }}
                    </button>
                </template>
            </form>
        </div>

        <UserPickerModal
            v-model="showPicker"
            title="Выбор пользователя для страйка"
            @select="selectUser"
        />
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.5rem; }

.compose-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(155,110,232,0.3);
    padding: 1.5rem;
    margin-bottom: 2rem;
    max-width: 600px;
}
.compose-title { font-size: 1rem; color: rgba(255,255,255,0.8); margin: 0 0 1.25rem; font-weight: 600; }

.compose-form { display: flex; flex-direction: column; gap: 1.25rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field-label { font-size: 0.78rem; color: rgba(255,255,255,0.4); letter-spacing: 0.04em; text-transform: uppercase; }
.field-input, .field-textarea {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    padding: 0.55rem 0.75rem; font-size: 0.88rem;
    outline: none; width: 100%; box-sizing: border-box;
    font-family: inherit;
}
.field-input:focus, .field-textarea:focus { border-color: rgba(155,110,232,0.6); }
.field-error { font-size: 0.78rem; color: #ff6b6b; margin: 0; }

.btn-send {
    align-self: flex-start;
    padding: 0.6rem 1.4rem;
    background: rgba(155,110,232,0.15); border: 1px solid rgba(155,110,232,0.45);
    color: #9B6EE8; font-size: 0.95rem; cursor: pointer; font-weight: 600;
}
.btn-send:hover { background: rgba(155,110,232,0.28); }
.btn-send:disabled { opacity: 0.5; }

/* User chip */
.user-chip {
    display: flex; align-items: center; gap: 0.75rem;
    background: rgba(155,110,232,0.08);
    border: 1px solid rgba(155,110,232,0.3);
    padding: 0.55rem 0.75rem;
}
.user-chip-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(155,110,232,0.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 600; color: #fff; flex-shrink: 0;
}
.user-chip-info { display: flex; flex-direction: column; gap: 0.1rem; flex: 1; min-width: 0; }
.user-chip-name { display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-chip-email { font-size: 0.75rem; color: rgba(255,255,255,0.4); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.badge-idol {
    background: rgba(255, 178, 239, 0.15);
    color: #ffb2ef;
    border: 1px solid rgba(255, 178, 239, 0.4);
    font-size: 0.65rem;
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    text-transform: uppercase;
    font-weight: bold;
}

.strikes-badge {
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.8);
    padding: 0.3rem 0.6rem;
    font-size: 0.75rem;
    border-radius: 4px;
    font-weight: 600;
}
.strikes-danger {
    background: rgba(229, 62, 62, 0.2);
    color: #fc8181;
    border: 1px solid rgba(229, 62, 62, 0.5);
}

.btn-change {
    padding: 0.28rem 0.65rem;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.14);
    color: rgba(255,255,255,0.6); font-size: 0.78rem; cursor: pointer; white-space: nowrap; flex-shrink: 0;
}
.btn-change:hover { background: rgba(255,255,255,0.12); }

.btn-pick {
    align-self: flex-start;
    padding: 0.45rem 0.95rem;
    background: rgba(155,110,232,0.1); border: 1px solid rgba(155,110,232,0.35);
    color: #9B6EE8; font-size: 0.85rem; cursor: pointer;
}
.btn-pick:hover { background: rgba(155,110,232,0.22); }

.ban-section {
    background: rgba(229, 62, 62, 0.05);
    border: 1px solid rgba(229, 62, 62, 0.3);
    padding: 1rem;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.ban-alert {
    color: #fc8181;
    font-size: 0.85rem;
    font-weight: 600;
}
</style>
