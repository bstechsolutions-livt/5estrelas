<script setup>
import { useDevice } from '@/composables/useDevice'
import PayablesDesktop from './Index.desktop.vue'
import PayablesMobile from './Index.mobile.vue'

defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    empresas: Array,
    filiais: Array,
    departments: Array,
    branches: Array,
    statusOptions: Object,
    canChangeDepartmentFilter: { type: Boolean, default: true },
    lockedDepartment: { type: Object, default: null },
    lockedBranches: { type: Array, default: () => [] },
    noBranchAccess: { type: Boolean, default: false },
    canManageClassification: { type: Boolean, default: false },
    canManagePriority: { type: Boolean, default: false },
    canLancar: { type: Boolean, default: false },
    priorityOptions: { type: Object, default: () => ({}) },
    canBypassApprovalDeadline: { type: Boolean, default: false },
    minDueDateForApproval: { type: String, default: null },
    syncStatus: { type: Object, default: null },
    canViewAwaitingSync: { type: Boolean, default: false },
    canAssignDepartmentSync: { type: Boolean, default: false },
})

const { isMobile } = useDevice()
</script>

<template>
    <PayablesMobile v-if="isMobile" v-bind="$props" />
    <PayablesDesktop v-else v-bind="$props" />
</template>
