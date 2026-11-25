<script setup>
import AdminPaginationUI from './UI/AdminPagination.vue';

defineProps({
    pagination: {
        type: Object,
        default: null,
    },
    links: {
        type: Array,
        default: null,
    },
});
</script>

<template>
    <AdminPaginationUI :pagination="pagination" :links="links" />
</template>
