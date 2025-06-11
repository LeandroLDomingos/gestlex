<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup } from '@/components/ui/select';
import {
    Edit, Trash2, PlusCircle, Paperclip, Clock, UserCircle2, Link as LinkIcon,
    MessageSquare, History, Briefcase, DollarSign, Users,
    CalendarDays, AlertTriangle, CheckCircle, Zap, MoreVertical, Archive, FileText, ChevronDownIcon, ArchiveRestore, Download, UploadCloud, ListChecks, Edit3, HandCoins, Edit2
} from 'lucide-vue-next';

import type { ProcessAnnotation, ProcessTask, ProcessDocument as ProcessDocumentType, ProcessHistoryEntry, BreadcrumbItem, UserReference } from '@/types/process';

// Interface para os dados de um pagamento individual
interface PaymentData {
    id: string | number;
    process_id: string | number;
    total_amount: number | string | null;
    down_payment_amount: number | string | null;
    payment_type: string | null;
    payment_method: string | null;
    down_payment_date: string | null;
    number_of_installments: number | null;
    value_of_installment: number | string | null;
    interest_amount?: number | string | null;
    status: string | null;
    status_label?: string;
    first_installment_due_date: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

// Interface para dados do formulário de Adicionar/Editar Honorários
interface FeeFormData {
    description: string;
    amount: number | string | null;
    is_installment?: boolean;
    number_of_installments?: number | string | null;
    first_installment_date?: string | null;
    is_first_payment_paid?: boolean;
    actual_payment_date?: string | null;
    fee_date?: string | null;
    payment_date?: string | null;
    is_paid?: boolean;
    payment_method: string | null;
    notes: string | null;
}

// Interface para formulário de edição de pagamentos de contrato/parcelas
interface EditPaymentFormData {
    payment_date: string | null;
    status: string | null;
    interest_amount: number | string | null;
}

interface ProcessDetails extends Omit<import('@/types/process').Process, 'negotiated_value' | 'payments'> {
    archived_at?: string | null;
    documents?: ProcessDocumentType[];
    history_entries?: ProcessHistoryEntry[];
    tasks?: ProcessTask[];
    payments?: PaymentData[];
    contact?: { id: number | string; name?: string | null; business_name?: string | null; };
    responsible?: UserReference | null;
}

interface SelectOption {
    key: string;
    label: string;
}
interface StageOption {
    key: number;
    label: string;
}

interface ContractOption {
    id: string;
    label: string;
    action: (process: ProcessDetails) => void;
}

const props = defineProps<{
    process: ProcessDetails;
    availableStages?: StageOption[];
    availablePriorities?: SelectOption[];
    availableStatuses?: SelectOption[];
    users?: UserReference[];
    paymentTypes?: Array<{ value: string; label: string }>;
    paymentStatuses?: Array<{ key: string; label: string }>;
    paymentMethods: string[];
}>();

const activeMainTab = ref<'tasks' | 'payments' | 'documents' | 'history'>('tasks');
const showNewAnnotationForm = ref(false);
const showDeleteProcessDialog = ref(false);
const processDeleteForm = useForm({});
const showDeleteProcessAnnotationDialog = ref(false);
const processAnnotationToDelete = ref<ProcessAnnotation | null>(null);
const processAnnotationDeleteForm = useForm({});

const stageUpdateForm = useForm({ stage: props.process.stage });
const statusUpdateForm = useForm({ status: props.process.status });
const priorityUpdateForm = useForm({ priority: props.process.priority });
const archiveForm = useForm({});

const showUploadProcessDocumentDialog = ref(false);
const processDocumentForm = useForm<{ file: File | null; description: string; }>({ file: null, description: '' });
const processDocumentFileInputRef = ref<HTMLInputElement | null>(null);
const showDeleteProcessDocumentDialog = ref(false);
const processDocumentToDelete = ref<ProcessDocumentType | null>(null);
const processDocumentDeleteForm = useForm({});

const showTaskDialog = ref(false);
const editingTask = ref<ProcessTask | null>(null);
const taskForm = useForm({
    id: null as (string | number | null),
    title: '',
    description: '',
    due_date: '',
    responsible_user_id: null as (string | number | null),
    status: 'Pendente',
});
const showDeleteTaskDialog = ref(false);
const taskToDelete = ref<ProcessTask | null>(null);
const taskDeleteForm = useForm({});

const showAddFeeDialog = ref(false);
const feeForm = useForm<FeeFormData>({
    description: '',
    amount: null,
    is_installment: false,
    number_of_installments: null,
    first_installment_date: new Date().toISOString().split('T')[0],
    is_first_payment_paid: false,
    actual_payment_date: null,
    payment_method: null,
    notes: '',
});

const showEditFeeDialog = ref(false);
const editingFee = ref<PaymentData | null>(null);
const editFeeForm = useForm<Omit<FeeFormData, 'is_installment' | 'number_of_installments' | 'first_installment_date' | 'is_first_payment_paid' | 'actual_payment_date'> & { fee_date: string | null, payment_date: string | null, is_paid: boolean }>({
    description: '',
    amount: null,
    fee_date: '',
    payment_method: null,
    payment_date: null,
    is_paid: false,
    notes: '',
});

const showEditPaymentDialog = ref(false);
const editingPayment = ref<PaymentData | null>(null);
const editPaymentForm = useForm<EditPaymentFormData>({
    payment_date: null,
    status: null,
    interest_amount: null,
});

const expandedNotes = ref<Record<string, boolean>>({});
const isNoteInDialogExpanded = ref(false);

function toggleNote(paymentId: string | number) {
    expandedNotes.value[String(paymentId)] = !expandedNotes.value[String(paymentId)];
}

const showInterestFieldForEditPayment = computed(() => {
    if (editingPayment.value && editPaymentForm.status === 'paid' && editPaymentForm.payment_date) {
        const paymentDateStr = editPaymentForm.payment_date;
        const dueDateStr = (editingPayment.value.payment_type === 'a_vista' && editingPayment.value.down_payment_amount && parseFloat(String(editingPayment.value.down_payment_amount)) > 0 && editingPayment.value.total_amount === editingPayment.value.down_payment_amount)
            ? editingPayment.value.down_payment_date
            : editingPayment.value.first_installment_due_date;

        if (!paymentDateStr || !dueDateStr) return false;
        try {
            const paymentDate = new Date(paymentDateStr + 'T00:00:00Z');
            const dueDate = new Date(formatDateForInput(dueDateStr) + 'T00:00:00Z');
            if (isNaN(paymentDate.getTime()) || isNaN(dueDate.getTime())) return false;
            return paymentDate.getTime() > dueDate.getTime();
        } catch (e) {
            console.error("Erro ao comparar datas para campo de juros:", e);
            return false;
        }
    }
    return false;
});

watch(() => feeForm.is_installment, (isInstallment) => {
    if (!isInstallment) {
        feeForm.number_of_installments = null;
        feeForm.clearErrors('number_of_installments');
    }
});
watch(() => feeForm.is_first_payment_paid, (isPaid) => {
    if (isPaid) {
        if (!feeForm.actual_payment_date) {
            feeForm.actual_payment_date = new Date().toISOString().split('T')[0];
        }
    } else {
        feeForm.actual_payment_date = null;
        feeForm.clearErrors('actual_payment_date');
    }
});

watch(() => editFeeForm.is_paid, (isPaid) => {
    if (isPaid) {
        if (!editFeeForm.payment_date) {
            editFeeForm.payment_date = new Date().toISOString().split('T')[0];
        }
    } else {
        editFeeForm.payment_date = null;
        editFeeForm.clearErrors('payment_date');
    }
});

watch(() => editPaymentForm.status, (newStatus) => {
    if (newStatus === 'paid') {
        if (!editPaymentForm.payment_date && editingPayment.value?.down_payment_date) {
            editPaymentForm.payment_date = formatDateForInput(editingPayment.value.down_payment_date);
        } else if (!editPaymentForm.payment_date) {
            editPaymentForm.payment_date = new Date().toISOString().split('T')[0];
        }
    } else {
        editPaymentForm.payment_date = null;
        editPaymentForm.interest_amount = null;
        editPaymentForm.clearErrors('payment_date', 'interest_amount');
    }
});

watch([() => editPaymentForm.payment_date, () => editPaymentForm.status], ([newPaymentDate, newStatus]) => {
    if (newStatus === 'paid' && newPaymentDate && editingPayment.value) {
        const dueDateStr = (editingPayment.value.payment_type === 'a_vista' && editingPayment.value.down_payment_amount && parseFloat(String(editingPayment.value.down_payment_amount)) > 0 && editingPayment.value.total_amount === editingPayment.value.down_payment_amount)
            ? editingPayment.value.down_payment_date
            : editingPayment.value.first_installment_due_date;
        if (!dueDateStr) return;
        try {
            const paymentDate = new Date(newPaymentDate + 'T00:00:00Z');
            const dueDate = new Date(formatDateForInput(dueDateStr) + 'T00:00:00Z');
            if (isNaN(paymentDate.getTime()) || isNaN(dueDate.getTime())) return;
            if (paymentDate <= dueDate) {
                editPaymentForm.interest_amount = null;
                editPaymentForm.clearErrors('interest_amount');
            }
        } catch (e) { /* Ignora erros */ }
    } else if (newStatus !== 'paid') {
        editPaymentForm.interest_amount = null;
        editPaymentForm.clearErrors('interest_amount');
    }
}, { deep: true });


const taskStatusOptions: SelectOption[] = [
    { key: 'Pendente', label: 'Pendente' },
    { key: 'Em Andamento', label: 'Em Andamento' },
    { key: 'Concluída', label: 'Concluída' },
    { key: 'Cancelada', label: 'Cancelada' },
];

const RGlobal = (window as any).route;
const routeHelper = (name?: string, params?: any, absolute?: boolean): string => {
    if (typeof RGlobal === 'function') { return RGlobal(name, params, absolute); }
    console.warn(`Helper de rota Ziggy não encontrado para a rota: ${name}. Usando fallback.`);
    let url = `/${name?.replace(/\./g, '/') || ''}`;
    if (params) {
        if (typeof params === 'object' && params !== null && !Array.isArray(params)) {
            Object.keys(params).forEach(key => {
                const paramPlaceholder = `:${key}`; const paramPlaceholderBraces = `{${key}}`;
                if (url.includes(paramPlaceholder)) { url = url.replace(paramPlaceholder, String(params[key])); }
                else if (url.includes(paramPlaceholderBraces)) { url = url.replace(paramPlaceholderBraces, String(params[key])); }
                else if (Object.keys(params).length === 1 && !url.includes(String(params[key]))) {
                    const paramValueString = String(params[key]);
                    if (url.split('/').pop() !== paramValueString) { url += `/${paramValueString}`; }
                }
            });
        } else if (typeof params !== 'object') { url += `/${params}`; }
    }
    return url;
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Casos', href: routeHelper('processes.index') },
    { title: props.process.workflow_label || props.process.workflow || 'Workflow', href: routeHelper('processes.index', { workflow: props.process.workflow }) },
    { title: props.process.title || 'Detalhes do Caso' },
]);

const formatDate = (dateString: string | null | undefined, includeTimeOrOptions: boolean | Intl.DateTimeFormatOptions = false): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        if (isNaN(date.getTime())) return dateString;

        let options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' };
        if (typeof includeTimeOrOptions === 'boolean' && includeTimeOrOptions) {
            options.hour = '2-digit'; options.minute = '2-digit';
        } else if (typeof includeTimeOrOptions === 'object') {
            options = {...options, ...includeTimeOrOptions};
        }
        return date.toLocaleDateString('pt-BR', options);
    } catch (e) { console.error("Erro ao formatar data:", dateString, e); return dateString; }
};


const formatDateForInput = (dateString: string | null | undefined): string => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        if (isNaN(date.getTime())) {
            const parts = dateString.split('/');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            return '';
        }
        return date.toISOString().split('T')[0];
    } catch (e) {
        console.error("Erro ao formatar data para input:", dateString, e);
        return '';
    }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'N/A';
    return numValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};
const getPaymentTypeLabel = (typeKey: string | null): string => {
    if (!typeKey) return 'N/A';
    const foundType = props.paymentTypes?.find(pt => pt.value === typeKey);
    if (foundType) return foundType.label;
    return typeKey.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};
const getPaymentStatusLabel = (statusKey: string | null): string => {
    if (!statusKey) return 'N/A';
    const foundStatus = props.paymentStatuses?.find(s => s.key === statusKey);
    if (foundStatus) return foundStatus.label;
    return statusKey.charAt(0).toUpperCase() + statusKey.slice(1);
};
const priorityLabelForDisplay = computed(() => props.process.priority_label || props.process.priority || 'N/A');
const priorityVariantForDisplay = computed((): 'destructive' | 'secondary' | 'outline' | 'default' => {
    if (!props.process.priority) return 'outline';
    switch (props.process.priority.toLowerCase()) {
        case 'high': return 'destructive';
        case 'medium': return 'secondary';
        case 'low': return 'outline';
        default: return 'outline';
    }
});

const annotationForm = useForm({ content: '' });
function submitAnnotation() {
    annotationForm.post(routeHelper('processes.annotations.store', props.process.id), {
        preserveScroll: true,
        onSuccess: () => { annotationForm.reset('content'); showNewAnnotationForm.value = false; router.reload({ only: ['process'] }); },
        onError: (errors) => console.error("Erro ao salvar anotação:", errors)
    });
}
function editProcess() { router.visit(routeHelper('processes.edit', props.process.id)); }
function toggleArchiveProcess() {
    const targetRoute = props.process.archived_at ? 'processes.unarchive' : 'processes.archive';
    archiveForm.patch(routeHelper(targetRoute, props.process.id), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => console.error(`Erro ao ${props.process.archived_at ? 'restaurar' : 'arquivar'} processo:`, errors)
    });
}
function openDeleteProcessDialog() { showDeleteProcessDialog.value = true; }
function submitDeleteProcess() {
    processDeleteForm.delete(routeHelper('processes.destroy', props.process.id), {
        preserveScroll: false,
        onSuccess: () => showDeleteProcessDialog.value = false,
        onError: (errors) => console.error('Erro ao excluir processo:', errors)
    });
}
function openDeleteProcessAnnotationDialog(annotation: ProcessAnnotation) {
    processAnnotationToDelete.value = annotation;
    showDeleteProcessAnnotationDialog.value = true;
}
function submitDeleteProcessAnnotation() {
    if (!processAnnotationToDelete.value) return;
    processAnnotationDeleteForm.delete(routeHelper('processes.annotations.destroy', { process: props.process.id, annotation: processAnnotationToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => { showDeleteProcessAnnotationDialog.value = false; processAnnotationToDelete.value = null; router.reload({ only: ['process'] }); },
        onError: (errors) => console.error('Erro ao excluir anotação do processo:', errors)
    });
}
function updateStage(newStageKey: number) {
    stageUpdateForm.stage = newStageKey;
    stageUpdateForm.patch(routeHelper('processes.updateStage', props.process.id), {
        preserveScroll: true, onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => { console.error('Erro ao atualizar estágio:', errors); stageUpdateForm.stage = props.process.stage; alert(errors.stage || 'Erro ao atualizar estágio.'); }
    });
}
function updateProcessStatus(newStatusKey: string) {
    statusUpdateForm.status = newStatusKey;
    statusUpdateForm.patch(routeHelper('processes.updateStatus', props.process.id), {
        preserveScroll: true, onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => { console.error('Erro ao atualizar status:', errors); statusUpdateForm.status = props.process.status; alert(errors.status || 'Erro ao atualizar status.'); }
    });
}
function updateProcessPriority(newPriorityKey: string) {
    priorityUpdateForm.priority = newPriorityKey;
    priorityUpdateForm.patch(routeHelper('processes.updatePriority', props.process.id), {
        preserveScroll: true, onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => { console.error('Erro ao atualizar prioridade:', errors); priorityUpdateForm.priority = props.process.priority; alert(errors.priority || 'Erro ao atualizar prioridade.'); }
    });
}
function submitProcessDocument() {
    if (!processDocumentForm.file) { processDocumentForm.setError('file', 'Selecione um arquivo.'); return; }
    processDocumentForm.post(routeHelper('processes.documents.store', props.process.id), {
        preserveScroll: true,
        onSuccess: () => { processDocumentForm.reset(); if (processDocumentFileInputRef.value) processDocumentFileInputRef.value.value = ''; showUploadProcessDocumentDialog.value = false; router.reload({ only: ['process'] }); },
        onError: (errors) => console.error('Erro ao enviar documento:', errors),
        forceFormData: true,
    });
}
function openDeleteProcessDocumentDialog(doc: ProcessDocumentType) { processDocumentToDelete.value = doc; showDeleteProcessDocumentDialog.value = true; }
function submitDeleteProcessDocument() {
    if (!processDocumentToDelete.value) return;
    processDocumentDeleteForm.delete(routeHelper('processes.documents.destroy', { process: props.process.id, document: processDocumentToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => { showDeleteProcessDocumentDialog.value = false; processDocumentToDelete.value = null; router.reload({ only: ['process'] }); },
        onError: (errors) => console.error('Erro ao excluir documento:', errors)
    });
}
function openNewTaskModal() {
    editingTask.value = null;
    taskForm.reset();
    taskForm.status = 'Pendente';
    taskForm.id = null;
    showTaskDialog.value = true;
}
function openEditTaskModal(task: ProcessTask) {
    editingTask.value = task;
    taskForm.id = task.id;
    taskForm.title = task.title;
    taskForm.description = task.description || '';
    taskForm.due_date = task.due_date ? formatDateForInput(task.due_date) : '';
    taskForm.responsible_user_id = task.responsible_user_id ? String(task.responsible_user_id) : null;
    taskForm.status = task.status || 'Pendente';
    showTaskDialog.value = true;
}
function submitProcessTask() {
    const dataToSubmit = {
        ...taskForm.data(),
        responsible_user_id: taskForm.responsible_user_id === 'null' || taskForm.responsible_user_id === '' ? null : taskForm.responsible_user_id,
    };

    if (editingTask.value) {
        taskForm.transform(() => dataToSubmit).put(routeHelper('processes.tasks.update', { process: props.process.id, task: editingTask.value!.id }), {
            preserveScroll: true,
            onSuccess: () => {
                showTaskDialog.value = false;
                taskForm.reset();
                editingTask.value = null;
                router.reload({ only: ['process'] });
            },
            onError: (errors) => console.error('Erro ao atualizar tarefa:', errors)
        });
    } else {
        taskForm.transform(() => dataToSubmit).post(routeHelper('processes.tasks.store', props.process.id), {
            preserveScroll: true,
            onSuccess: () => {
                showTaskDialog.value = false;
                taskForm.reset();
                router.reload({ only: ['process'] });
            },
            onError: (errors) => console.error('Erro ao criar tarefa:', errors)
        });
    }
}
function openDeleteTaskDialog(task: ProcessTask) {
    taskToDelete.value = task;
    showDeleteTaskDialog.value = true;
}
function submitDeleteTask() {
    if (!taskToDelete.value) return;
    taskDeleteForm.delete(routeHelper('processes.tasks.destroy', { process: props.process.id, task: taskToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteTaskDialog.value = false;
            taskToDelete.value = null;
            router.reload({ only: ['process'] });
        },
        onError: (errors) => console.error('Erro ao excluir tarefa:', errors)
    });
}

function openAddFeeDialog() {
    feeForm.reset();
    feeForm.first_installment_date = new Date().toISOString().split('T')[0];
    showAddFeeDialog.value = true;
}

function submitFee() {
    const dataToSubmit: Record<string, any> = {
        description: feeForm.description,
        amount: feeForm.amount,
        is_installment: feeForm.is_installment,
        payment_method: feeForm.payment_method,
        notes: feeForm.notes,
    };

    if (feeForm.is_installment) {
        dataToSubmit.number_of_installments = feeForm.number_of_installments;
        dataToSubmit.first_installment_date = feeForm.first_installment_date;
        dataToSubmit.is_first_payment_paid = feeForm.is_first_payment_paid;
        dataToSubmit.actual_payment_date = feeForm.is_first_payment_paid ? feeForm.actual_payment_date : null;
    } else {
        dataToSubmit.fee_date = feeForm.first_installment_date;
        dataToSubmit.is_paid = feeForm.is_first_payment_paid;
        dataToSubmit.payment_date = feeForm.is_first_payment_paid ? feeForm.actual_payment_date : null;
    }

    router.post(routeHelper('processes.fees.store', props.process.id), dataToSubmit, {
        preserveScroll: true,
        onSuccess: () => {
            showAddFeeDialog.value = false;
            feeForm.reset();
            feeForm.first_installment_date = new Date().toISOString().split('T')[0];
            router.reload({ only: ['process'] });
        },
        onError: (errors) => {
            console.error('Erro ao adicionar honorários:', errors);
            Object.keys(errors).forEach(key => {
                if (key in feeForm.errors) {
                    (feeForm.errors as Record<string, string>)[key] = errors[key];
                }
            });
        }
    });
}

function openEditFeeDialog(fee: PaymentData) {
    editingFee.value = fee;
    const dbNotes = fee.notes || '';
    const separator = "\nObservações Adicionais: ";
    const separatorIndex = dbNotes.indexOf(separator);

    if (separatorIndex !== -1 && fee.payment_type === 'honorario') {
        editFeeForm.description = dbNotes.substring(0, separatorIndex);
        editFeeForm.notes = dbNotes.substring(separatorIndex + separator.length);
    } else {
        editFeeForm.description = dbNotes;
        editFeeForm.notes = '';
    }

    editFeeForm.amount = fee.total_amount;
    editFeeForm.fee_date = fee.first_installment_due_date ? formatDateForInput(fee.first_installment_due_date) : '';
    editFeeForm.payment_method = fee.payment_method;
    editFeeForm.is_paid = fee.status === 'paid';

    if (editFeeForm.is_paid && fee.down_payment_date) {
        editFeeForm.payment_date = formatDateForInput(fee.down_payment_date);
    } else if (editFeeForm.is_paid && !fee.down_payment_date) {
        editFeeForm.payment_date = new Date().toISOString().split('T')[0];
    } else {
        editFeeForm.payment_date = null;
    }
    editFeeForm.clearErrors();
    showEditFeeDialog.value = true;
}

function submitEditFee() {
    if (!editingFee.value || !editingFee.value.id) return;
    const dataToSubmit = {
        description: editFeeForm.description,
        amount: editFeeForm.amount,
        fee_date: editFeeForm.fee_date,
        payment_method: editFeeForm.payment_method,
        is_paid: editFeeForm.is_paid,
        payment_date: editFeeForm.is_paid && editFeeForm.payment_date ? editFeeForm.payment_date : null,
        notes: editFeeForm.notes,
    };
    router.put(routeHelper('processes.fees.update', { process: props.process.id, fee: editingFee.value!.id }), dataToSubmit, {
        preserveScroll: true,
        onSuccess: () => {
            showEditFeeDialog.value = false;
            editingFee.value = null;
            editFeeForm.reset();
            router.reload({ only: ['process'] });
        },
        onError: (errors) => {
            console.error('Erro ao atualizar honorários:', errors);
            Object.keys(errors).forEach(key => {
                if (key in editFeeForm.errors) {
                    (editFeeForm.errors as Record<string, string>)[key] = errors[key];
                }
            });
        }
    });
}

function openEditPaymentModal(payment: PaymentData) {
    editingPayment.value = payment;
    isNoteInDialogExpanded.value = false;
    editPaymentForm.status = payment.status;

    if (payment.status === 'paid' && payment.down_payment_date) {
        editPaymentForm.payment_date = formatDateForInput(payment.down_payment_date);
    } else if (payment.status === 'paid' && !payment.down_payment_date) {
        editPaymentForm.payment_date = new Date().toISOString().split('T')[0];
    } else {
        editPaymentForm.payment_date = null;
    }
    editPaymentForm.interest_amount = payment.interest_amount ? String(payment.interest_amount) : null;

    editPaymentForm.clearErrors();
    showEditPaymentDialog.value = true;
}

function submitEditPayment() {
    if (!editingPayment.value || !editingPayment.value.id) return;

    const dataToSubmit: EditPaymentFormData = {
        status: editPaymentForm.status,
        payment_date: null,
        interest_amount: null,
    };

    if (editPaymentForm.status === 'paid') {
        if (!editPaymentForm.payment_date) {
            editPaymentForm.setError('payment_date', 'Data de pagamento é obrigatória para status Pago.');
            return;
        }
        dataToSubmit.payment_date = editPaymentForm.payment_date;

        if (showInterestFieldForEditPayment.value && editPaymentForm.interest_amount) {
            const interest = parseFloat(String(editPaymentForm.interest_amount));
            dataToSubmit.interest_amount = !isNaN(interest) ? interest : null;
        } else {
            dataToSubmit.interest_amount = null;
        }
    } else {
        dataToSubmit.payment_date = null;
        dataToSubmit.interest_amount = null;
    }

    router.put(routeHelper('processes.payments.update', { process: props.process.id, payment: editingPayment.value.id }), dataToSubmit, {
        preserveScroll: true,
        onSuccess: () => {
            showEditPaymentDialog.value = false;
            editingPayment.value = null;
            editPaymentForm.reset();
            router.reload({ only: ['process'] });
        },
        onError: (errors) => {
            console.error('Erro ao atualizar pagamento:', errors);
            Object.keys(errors).forEach(key => {
                if (key in editPaymentForm.errors) {
                    (editPaymentForm.errors as Record<string, string>)[key] = errors[key];
                }
            });
        }
    });
}

const contractTypes = ref<ContractOption[]>([
    {
        id: 'procuracao',
        label: 'Procuração',
        action: (process) => {
            const url = routeHelper('processes.documents.show.aposentadoria.form', { processo: process.id });
            window.open(url, '_blank');
        }
    },
    {
        id: 'aposentadoria',
        label: 'Contrato de Aposentadoria',
        action: (process) => {
            const url = routeHelper('processes.documents.show.aposentadoria.form', { processo: process.id });
            window.open(url, '_blank');
        }
    },
]);

const handleGenerateContract = (option: ContractOption) => {
    if (option.action) {
        option.action(props.process);
    } else {
        console.warn('Nenhuma ação definida para a opção de contrato:', option.label);
    }
};


const isArchived = computed(() => !!props.process.archived_at);
const totalPaymentsAmount = computed(() => {
    if (!props.process.payments || props.process.payments.length === 0) {
        return null;
    }
    return props.process.payments.reduce((sum, payment) => {
        let paymentValue = Number(payment.total_amount) || 0;
        if ((payment.status === 'paid' || payment.status === 'pending') && payment.interest_amount && parseFloat(String(payment.interest_amount)) > 0) {
            paymentValue += parseFloat(String(payment.interest_amount));
        }
        return sum + paymentValue;
    }, 0);
});

</script>

<template>
    <Head :title="`Caso: ${process.title || 'Detalhes do Caso'}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col lg:flex-row gap-6 p-4 md:p-6 h-[calc(100vh-theme(spacing.16)-theme(spacing.1))] overflow-hidden">
            <div class="w-full lg:w-1/3 xl:w-1/4 space-y-6 flex-shrink-0 overflow-y-auto pr-2 no-scrollbar">
                <Card class="overflow-hidden shadow-lg">
                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-700 dark:to-indigo-800 h-32 sm:h-40 md:h-48 flex items-center justify-center p-4 relative">
                        <Briefcase v-if="process.workflow_label?.toLowerCase().includes('judicial')" class="h-16 w-16 text-white opacity-75" />
                        <MessageSquare v-else-if="process.workflow_label?.toLowerCase().includes('consultivo')" class="h-16 w-16 text-white opacity-75" />
                        <Zap v-else-if="process.workflow_label?.toLowerCase().includes('prospecção')" class="h-16 w-16 text-white opacity-75" />
                        <FileText v-else class="h-16 w-16 text-white opacity-75" />
                        <div class="absolute top-2 right-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-white hover:bg-white/20 focus-visible:ring-white/50">
                                        <MoreVertical class="h-5 w-5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuLabel>Ações do Caso</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="editProcess" :disabled="isArchived">
                                        <Edit class="mr-2 h-4 w-4" />
                                        <span>Editar</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="toggleArchiveProcess">
                                        <Archive v-if="!isArchived" class="mr-2 h-4 w-4" />
                                        <ArchiveRestore v-else class="mr-2 h-4 w-4" />
                                        <span>{{ isArchived ? 'Restaurar' : 'Arquivar' }}</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="openDeleteProcessDialog" class="text-red-600 focus:bg-red-50 dark:focus:bg-red-900/50 focus:text-red-600 dark:focus:text-red-400">
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        <span>Excluir</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                    <CardContent class="p-4 space-y-2">
                         <div v-if="isArchived" class="mb-2 p-2 bg-yellow-100 dark:bg-yellow-900/50 border-l-4 border-yellow-500 text-yellow-700 dark:text-yellow-300 text-xs rounded">
                            <p class="font-medium">Este caso está arquivado.</p>
                            <p>Arquivado em: {{ formatDate(process.archived_at, true) }}</p>
                        </div>
                        <CardTitle class="text-lg font-semibold text-gray-800 dark:text-gray-100 truncate" :title="process.title">
                            {{ process.title || 'Caso sem Título' }}
                        </CardTitle>
                        <CardDescription class="text-xs text-gray-600 dark:text-gray-400 flex items-center flex-wrap">
                            <span>{{ process.workflow_label || process.workflow }} - Estágio:</span>
                            <DropdownMenu v-if="props.availableStages && props.availableStages.length > 0 && !isArchived">
                                <DropdownMenuTrigger as-child>
                                    <Button variant="link" class="p-0 h-auto ml-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline focus-visible:ring-0 focus-visible:ring-offset-0 inline-flex items-center" :disabled="stageUpdateForm.processing">
                                        {{ process.stage_label || process.stage || 'N/A' }}
                                        <ChevronDownIcon class="h-3 w-3 ml-0.5 opacity-70" v-if="!stageUpdateForm.processing" />
                                        <svg v-else class="animate-spin ml-1 h-3 w-3 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuLabel>Mudar Estágio</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuRadioGroup v-model="stageUpdateForm.stage" @update:modelValue="updateStage">
                                        <DropdownMenuRadioItem
                                            v-for="stageOption in props.availableStages"
                                            :key="stageOption.key"
                                            :value="stageOption.key"
                                            class="text-xs"
                                            :disabled="stageUpdateForm.processing || stageUpdateForm.stage === stageOption.key"
                                        >
                                            {{ stageOption.label }}
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <span v-else class="ml-1">{{ process.stage_label || process.stage || 'N/A' }}</span>
                        </CardDescription>
                        <div v-if="stageUpdateForm.errors.stage" class="text-xs text-red-500 mt-1">
                            {{ stageUpdateForm.errors.stage }}
                        </div>

                        <Separator class="my-3" />

                        <div class="text-sm space-y-1.5 text-gray-700 dark:text-gray-300">
                            <div v-if="process.contact" class="flex items-center">
                                <Users class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Contato:</span>
                                <Link :href="routeHelper('contacts.show', process.contact.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline truncate">
                                    {{ process.contact.name || process.contact.business_name || 'N/A' }}
                                </Link>
                            </div>
                             <div v-if="process.responsible" class="flex items-center">
                                <UserCircle2 class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Responsável:</span> {{ process.responsible.name || 'N/A' }}
                            </div>

                            <div class="flex items-center">
                                <CheckCircle class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Status:</span>
                                <DropdownMenu v-if="props.availableStatuses && props.availableStatuses.length > 0 && !isArchived">
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="link" class="p-0 h-auto text-sm text-indigo-600 dark:text-indigo-400 hover:underline focus-visible:ring-0 focus-visible:ring-offset-0 inline-flex items-center" :disabled="statusUpdateForm.processing">
                                            {{ process.status_label || process.status || 'N/A' }}
                                            <ChevronDownIcon class="h-3 w-3 ml-0.5 opacity-70" v-if="!statusUpdateForm.processing" />
                                            <svg v-else class="animate-spin ml-1 h-3 w-3 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuLabel>Mudar Status</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuRadioGroup v-model="statusUpdateForm.status" @update:modelValue="updateProcessStatus">
                                            <DropdownMenuRadioItem
                                                v-for="statusOption in props.availableStatuses"
                                                :key="statusOption.key"
                                                :value="statusOption.key"
                                                class="text-xs"
                                                :disabled="statusUpdateForm.processing || statusUpdateForm.status === statusOption.key"
                                            >
                                                {{ statusOption.label }}
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <span v-else class="ml-1">{{ process.status_label || process.status || 'N/A' }}</span>
                                <div v-if="statusUpdateForm.errors.status" class="text-xs text-red-500 ml-2">
                                    {{ statusUpdateForm.errors.status }}
                                </div>
                            </div>

                            <div class="flex items-center">
                                <AlertTriangle class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Prioridade:</span>
                                <DropdownMenu v-if="props.availablePriorities && props.availablePriorities.length > 0 && !isArchived">
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="link" class="p-0 h-auto text-sm focus-visible:ring-0 focus-visible:ring-offset-0 inline-flex items-center" :disabled="priorityUpdateForm.processing">
                                            <Badge :variant="priorityVariantForDisplay" class="text-xs">
                                                {{ priorityLabelForDisplay }}
                                            </Badge>
                                            <ChevronDownIcon class="h-3 w-3 ml-0.5 opacity-70 text-gray-600 dark:text-gray-400" v-if="!priorityUpdateForm.processing"/>
                                            <svg v-else class="animate-spin ml-1 h-3 w-3 text-gray-600 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuLabel>Mudar Prioridade</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuRadioGroup v-model="priorityUpdateForm.priority" @update:modelValue="updateProcessPriority">
                                            <DropdownMenuRadioItem
                                                v-for="priorityOption in props.availablePriorities"
                                                :key="priorityOption.key"
                                                :value="priorityOption.key"
                                                class="text-xs"
                                                :disabled="priorityUpdateForm.processing || priorityUpdateForm.priority === priorityOption.key"
                                            >
                                                {{ priorityOption.label }}
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Badge v-else :variant="priorityVariantForDisplay" class="ml-1 text-xs">{{ priorityLabelForDisplay }}</Badge>
                                <div v-if="priorityUpdateForm.errors.priority" class="text-xs text-red-500 ml-2">
                                    {{ priorityUpdateForm.errors.priority }}
                                </div>
                            </div>

                            <div class="flex items-center">
                                <CalendarDays class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Criado em:</span> {{ formatDate(process.created_at) }}
                            </div>
                             <div v-if="process.due_date" class="flex items-center">
                                <Clock class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Vencimento do Caso:</span> {{ formatDate(process.due_date) }}
                            </div>
                             <div v-if="process.origin" class="flex items-center">
                                <LinkIcon class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Origem:</span> {{ process.origin }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex justify-between items-center">
                            <CardTitle class="text-lg">Anotações</CardTitle>
                            <Button variant="outline" size="sm" @click="showNewAnnotationForm = !showNewAnnotationForm" :disabled="isArchived">
                                <PlusCircle class="h-4 w-4 mr-2" /> Nova Anotação
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <form v-if="showNewAnnotationForm" @submit.prevent="submitAnnotation" class="space-y-2 mb-4">
                            <Textarea
                                v-model="annotationForm.content"
                                placeholder="Digite sua anotação aqui..."
                                rows="3"
                                class="text-sm"
                                :disabled="isArchived"
                            />
                            <div v-if="annotationForm.errors.content" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ annotationForm.errors.content }}
                            </div>
                            <div class="flex justify-end space-x-2">
                                <Button type="button" variant="ghost" size="sm" @click="showNewAnnotationForm = false; annotationForm.reset('content'); annotationForm.clearErrors();">Cancelar</Button>
                                <Button type="submit" size="sm" :disabled="annotationForm.processing || isArchived">Salvar</Button>
                            </div>
                        </form>

                        <div v-if="process.annotations && process.annotations.length > 0" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <div v-for="annotation in process.annotations.slice().reverse()" :key="annotation.id" class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md text-xs relative group">
                                <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ annotation.content }}</p>
                                <p class="text-gray-500 dark:text-gray-400 mt-1 text-right">
                                    {{ annotation.user_name || annotation.user?.name || 'Sistema' }} - {{ formatDate(annotation.created_at, true) }}
                                </p>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="absolute top-1 right-1 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
                                    @click="openDeleteProcessAnnotationDialog(annotation)"
                                    title="Excluir anotação"
                                    :disabled="isArchived"
                                >
                                    <Trash2 class="h-3 w-3 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-500" />
                                </Button>
                            </div>
                        </div>
                        <p v-else-if="!showNewAnnotationForm" class="text-gray-500 dark:text-gray-400 text-center py-4">Nenhuma anotação encontrada.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="w-full lg:w-2/3 xl:w-3/4 flex flex-col overflow-hidden">
                <div class="flex-shrink-0 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-2 px-1" aria-label="Tabs">
                        <button @click="activeMainTab = 'tasks'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'tasks' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            TAREFAS ({{ process.tasks?.length || 0 }})
                        </button>
                        <button @click="activeMainTab = 'payments'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'payments' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            PAGAMENTOS ({{ process.payments?.length || 0 }})
                        </button>
                        <button @click="activeMainTab = 'documents'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'documents' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            DOCUMENTOS ({{ process.documents?.length || 0 }})
                        </button>
                        <button @click="activeMainTab = 'history'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'history' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            HISTÓRICO ({{ process.history_entries?.length || 0 }})
                        </button>
                    </nav>
                </div>

                <div class="flex-grow overflow-y-auto p-1 pr-2 no-scrollbar">
                    <div v-if="activeMainTab === 'tasks'" class="space-y-4 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Lista de Tarefas</h3>
                            <Button variant="outline" size="sm" @click="openNewTaskModal" :disabled="isArchived">
                                <PlusCircle class="h-4 w-4 mr-2" /> Nova Tarefa
                            </Button>
                        </div>
                        <div v-if="process.tasks && process.tasks.length > 0" class="space-y-3">
                            <Card v-for="task_item in process.tasks" :key="task_item.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-4 flex items-start justify-between gap-3">
                                    <div class="flex-grow min-w-0">
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ task_item.title }}</p>
                                        <p v-if="task_item.description" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 whitespace-pre-wrap">{{ task_item.description }}</p>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-x-3 gap-y-1 flex-wrap">
                                            <span v-if="task_item.responsible_user" class="flex items-center"><UserCircle2 class="h-3.5 w-3.5 mr-1"/> {{ task_item.responsible_user?.name || 'N/A' }}</span>
                                            <span v-if="task_item.due_date" class="flex items-center"><Clock class="h-3.5 w-3.5 mr-1"/> Venc.: {{ formatDate(task_item.due_date) }}</span>
                                            <span v-if="task_item.updated_at && task_item.created_at && task_item.updated_at !== task_item.created_at"
                                                class="flex items-center"
                                                :title="`Criado em: ${formatDate(task_item.created_at, {hour: '2-digit', minute: '2-digit'})}`">
                                                <Edit2 class="h-3.5 w-3.5 mr-1.5 flex-shrink-0 text-gray-400 dark:text-gray-500"/>
                                                <span>Editado: {{ formatDate(task_item.updated_at, {hour: '2-digit', minute: '2-digit'}) }}</span>
                                            </span>
                                            <span v-else-if="task_item.created_at"
                                                class="flex items-center">
                                                <ListChecks class="h-3.5 w-3.5 mr-1.5 flex-shrink-0 text-gray-400 dark:text-gray-500"/>
                                                <span>Criado: {{ formatDate(task_item.created_at, {hour: '2-digit', minute: '2-digit'}) }}</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0 space-y-1">
                                        <Badge :variant="task_item.is_overdue ? 'destructive' : (task_item.status === 'Concluída' ? 'default' : 'outline')"
                                            class="text-xs whitespace-nowrap">
                                            {{ task_item.is_overdue ? 'Atrasada' : task_item.status }}
                                        </Badge>
                                        <div class="flex space-x-1 justify-end mt-1">
                                            <Button variant="ghost" size="icon" class="h-7 w-7" @click="openEditTaskModal(task_item)" :disabled="isArchived" title="Editar Tarefa">
                                                <Edit3 class="h-3.5 w-3.5 text-gray-500 hover:text-indigo-600" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="h-7 w-7" @click="openDeleteTaskDialog(task_item)" :disabled="isArchived" title="Excluir Tarefa">
                                                <Trash2 class="h-3.5 w-3.5 text-gray-500 hover:text-red-600" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhuma tarefa para este caso.</p>
                    </div>

                    <div v-if="activeMainTab === 'payments'" class="space-y-4 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Pagamentos Registrados</h3>
                            <Button variant="outline" size="sm" @click="openAddFeeDialog" :disabled="isArchived">
                                <HandCoins class="h-4 w-4 mr-2" /> Adicionar Honorários
                            </Button>
                        </div>
                        <div v-if="process.payments && process.payments.length > 0" class="space-y-3">
                            <Card v-for="payment_item in process.payments" :key="payment_item.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">{{ formatCurrency(payment_item.total_amount) }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                ID Transação: {{ payment_item.id }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Badge :variant="'outline'"
                                                :class="[
                                                    'text-xs capitalize',
                                                    payment_item.status === 'paid' ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-700/30 dark:text-green-300 dark:border-green-600' : '',
                                                    payment_item.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-700/30 dark:text-yellow-300 dark:border-yellow-600' : '',
                                                    (payment_item.status === 'failed' || payment_item.status === 'refunded' || payment_item.status === 'overdue') ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-700/30 dark:text-red-300 dark:border-red-600' : '',
                                                    !(payment_item.status === 'paid' || payment_item.status === 'pending' || payment_item.status === 'failed' || payment_item.status === 'refunded' || payment_item.status === 'overdue') ? 'border-gray-300 dark:border-gray-600' : ''
                                                ]">
                                                {{ payment_item.status_label || getPaymentStatusLabel(payment_item.status) }}
                                            </Badge>
                                            <Button
                                                v-if="!isArchived"
                                                variant="ghost"
                                                size="icon"
                                                class="h-7 w-7"
                                                @click="payment_item.payment_type === 'honorario' ? openEditFeeDialog(payment_item) : openEditPaymentModal(payment_item)"
                                                :title="payment_item.payment_type === 'honorario' ? 'Editar Honorário' : 'Editar Pagamento'"
                                            >
                                                <Edit3 class="h-3.5 w-3.5 text-gray-500 hover:text-indigo-600" />
                                            </Button>
                                        </div>
                                    </div>
                                    <Separator class="my-2" />
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-700 dark:text-gray-300">
                                        <p><span class="font-medium">Tipo:</span> {{ getPaymentTypeLabel(payment_item.payment_type) }}</p>
                                        <p><span class="font-medium">Método:</span> {{ payment_item.payment_method || 'N/A' }}</p>

                                        <template v-if="payment_item.payment_type === 'honorario'">
                                            <p><span class="font-medium">Vencimento:</span> {{ formatDate(payment_item.first_installment_due_date) }}</p>
                                            <p v-if="payment_item.down_payment_date"><span class="font-medium">Pago em:</span> {{ formatDate(payment_item.down_payment_date) }}</p>
                                        </template>
                                        <template v-else> <p><span class="font-medium">Vencimento:</span> {{ formatDate(payment_item.first_installment_due_date || payment_item.down_payment_date) }}</p>
                                            <p v-if="payment_item.status === 'paid' && payment_item.down_payment_date"><span class="font-medium">Pago em:</span> {{ formatDate(payment_item.down_payment_date) }}</p>
                                        </template>

                                        <p v-if="payment_item.interest_amount && parseFloat(String(payment_item.interest_amount)) > 0" class="text-red-600 dark:text-red-400">
                                            <span class="font-medium">Juros Pagos:</span> {{ formatCurrency(payment_item.interest_amount) }}
                                        </p>
                                    </div>
                                    <div v-if="payment_item.notes" class="text-xs text-gray-600 dark:text-gray-400 mt-2 break-words">
                                        <span class="font-medium">Observações:</span>
                                        <span>
                                            {{ (expandedNotes[payment_item.id] || (payment_item.notes && payment_item.notes.length <= 150)) ? payment_item.notes : `${payment_item.notes.substring(0, 150)}...` }}
                                        </span>
                                        <button
                                            v-if="payment_item.notes && payment_item.notes.length > 150"
                                            @click="toggleNote(payment_item.id)"
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs ml-1 font-semibold"
                                            type="button"
                                        >
                                            {{ expandedNotes[payment_item.id] ? 'Ver menos' : 'Ver mais' }}
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 text-right">Registrado em: {{ formatDate(payment_item.created_at, true) }}</p>
                                </CardContent>
                            </Card>
                             <Card class="bg-gray-50 dark:bg-gray-700/50 mt-4">
                                <CardContent class="p-3 text-right">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Soma dos Pagamentos Listados (Inclui Juros):</p>
                                    <p class="text-xl font-semibold text-indigo-700 dark:text-indigo-400">{{ formatCurrency(totalPaymentsAmount) }}</p>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum pagamento registrado para este caso.</p>
                    </div>

                    <div v-if="activeMainTab === 'documents'" class="space-y-4 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Documentos</h3>
                            <div class="flex space-x-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="outline" size="sm" :disabled="isArchived">
                                            <FileText class="h-4 w-4 mr-2" />
                                            Gerar Contrato
                                            <ChevronDownIcon class="h-4 w-4 ml-2" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-64">
                                        <DropdownMenuLabel>Selecione um Modelo de Contrato</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem v-for="option in contractTypes" :key="option.id" @click="handleGenerateContract(option)">
                                            <span>{{ option.label }}</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <Dialog :open="showUploadProcessDocumentDialog" @update:open="showUploadProcessDocumentDialog = $event">
                                    <DialogTrigger as-child>
                                        <Button variant="default" size="sm" @click="showUploadProcessDocumentDialog = true" :disabled="isArchived">
                                            <PlusCircle class="h-4 w-4 mr-2" /> Adicionar Documento
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent class="sm:max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>Adicionar Novo Documento ao Caso</DialogTitle>
                                            <DialogDescription>
                                                Selecione um arquivo e adicione uma descrição opcional.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <form @submit.prevent="submitProcessDocument" class="space-y-4 mt-4">
                                            <div>
                                                <Label for="processDocumentFile" class="text-sm font-medium">Arquivo</Label>
                                                <div class="mt-1 flex items-center h-10 border border-input bg-background rounded-md ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
                                                    <Input
                                                        id="processDocumentFile"
                                                        type="file"
                                                        ref="processDocumentFileInputRef"
                                                        @input="processDocumentForm.file = ($event.target as HTMLInputElement)?.files?.[0] || null"
                                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/50 dark:file:text-indigo-300 dark:hover:file:bg-indigo-800/50 h-full focus-visible:ring-0 focus-visible:ring-offset-0 border-0 shadow-none"
                                                        required
                                                    />
                                                </div>
                                                <div v-if="processDocumentForm.progress" class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-2">
                                                    <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: processDocumentForm.progress.percentage + '%' }"></div>
                                                </div>
                                                <div v-if="processDocumentForm.errors.file" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                    {{ processDocumentForm.errors.file }}
                                                </div>
                                            </div>
                                            <div>
                                                <Label for="processDocumentDescription" class="text-sm font-medium">Descrição (Opcional)</Label>
                                                <Textarea
                                                    id="processDocumentDescription"
                                                    v-model="processDocumentForm.description"
                                                    placeholder="Descrição breve do documento..."
                                                    rows="3"
                                                    class="mt-1 text-sm"
                                                />
                                                <div v-if="processDocumentForm.errors.description" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                    {{ processDocumentForm.errors.description }}
                                                </div>
                                            </div>
                                            <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                                                <DialogClose as-child>
                                                    <Button variant="outline" type="button" @click="showUploadProcessDocumentDialog = false; processDocumentForm.reset(); if(processDocumentFileInputRef) processDocumentFileInputRef.value = ''; processDocumentForm.clearErrors();">Cancelar</Button>
                                                </DialogClose>
                                                <Button type="submit" :disabled="processDocumentForm.processing">
                                                    <UploadCloud class="mr-2 h-4 w-4" v-if="!processDocumentForm.processing" />
                                                    <svg v-else class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    {{ processDocumentForm.processing ? 'Enviando...' : 'Enviar Documento' }}
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                        <div v-if="process.documents && process.documents.length > 0" class="space-y-3">
                            <Card v-for="doc in process.documents" :key="doc.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <Paperclip class="h-5 w-5 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                        <div class="flex-grow min-w-0">
                                            <a :href="doc.url || routeHelper('processes.documents.download', { process: process.id, document: doc.id })" target="_blank" :download="doc.file_name || doc.name" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ doc.file_name || doc.name }}</a>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                Enviado por: {{ doc.uploader?.name || 'N/A' }} em: {{ formatDate(doc.created_at) }}
                                                <span v-if="doc.size">({{ typeof doc.size === 'number' ? (doc.size / 1024 / 1024).toFixed(2) + ' MB' : doc.size }})</span>
                                            </p>
                                            <p v-if="doc.description" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 break-words">{{ doc.description }}</p>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 space-x-1">
                                        <a :href="doc.url || routeHelper('processes.documents.download', { process: process.id, document: doc.id })" target="_blank" :download="doc.file_name || doc.name">
                                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Baixar documento">
                                                <Download class="h-4 w-4 text-gray-500 hover:text-indigo-600" />
                                            </Button>
                                        </a>
                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="openDeleteProcessDocumentDialog(doc)" title="Excluir documento" :disabled="isArchived">
                                            <Trash2 class="h-4 w-4 text-gray-500 hover:text-red-600" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum documento anexado.</p>
                    </div>

                    <div v-if="activeMainTab === 'history'" class="space-y-4 py-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Histórico de Atividades</h3>
                        <div v-if="process.history_entries && process.history_entries.length > 0" class="space-y-3">
                            <Card v-for="entry in process.history_entries" :key="entry.id" class="bg-gray-50 dark:bg-gray-800/60">
                                <CardContent class="p-3 text-xs">
                                   <p><span class="font-semibold">{{ entry.user?.name || entry.user_name || 'Sistema' }}</span> {{ entry.action?.toLowerCase() || 'realizou uma ação' }}: <span class="text-gray-700 dark:text-gray-300">{{ entry.description }}</span></p>
                                    <p class="text-gray-500 dark:text-gray-400 mt-0.5">{{ formatDate(entry.created_at, true) }}</p>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum histórico de atividades.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- DIALOGS -->
        <Dialog :open="showDeleteProcessDialog" @update:open="showDeleteProcessDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão do Caso</DialogTitle>
                    <DialogDescription>
                        Tem certeza de que deseja excluir o caso <strong class="font-medium">"{{ process.title }}"</strong>?
                        Esta ação não poderá ser desfeita e todos os dados associados (tarefas, documentos, anotações, pagamentos) também poderão ser afetados.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <Button variant="outline" type="button" @click="showDeleteProcessDialog = false">Cancelar</Button>
                    <Button variant="destructive" :disabled="processDeleteForm.processing" @click="submitDeleteProcess">
                        <svg v-if="processDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ processDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDeleteProcessAnnotationDialog" @update:open="showDeleteProcessAnnotationDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Anotação</DialogTitle>
                    <DialogDescription v-if="processAnnotationToDelete">
                        Tem certeza de que deseja excluir esta anotação?
                        <blockquote class="mt-2 p-2 border-l-4 border-gray-300 bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-xs text-gray-600 dark:text-gray-300">
                            {{ processAnnotationToDelete.content.substring(0, 100) }}{{ processAnnotationToDelete.content.length > 100 ? '...' : '' }}
                        </blockquote>
                        Esta ação não poderá ser desfeita.
                    </DialogDescription>
                    <DialogDescription v-else>
                        Tem certeza de que deseja excluir esta anotação? Esta ação não poderá ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <Button variant="outline" type="button" @click="showDeleteProcessAnnotationDialog = false; processAnnotationToDelete = null;">Cancelar</Button>
                    <Button variant="destructive" :disabled="processAnnotationDeleteForm.processing" @click="submitDeleteProcessAnnotation">
                        <svg v-if="processAnnotationDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ processAnnotationDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDeleteProcessDocumentDialog" @update:open="showDeleteProcessDocumentDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Documento</DialogTitle>
                    <DialogDescription v-if="processDocumentToDelete">
                        Tem certeza de que deseja excluir o documento <strong class="font-medium">"{{ processDocumentToDelete.name }}"</strong>? Esta ação não poderá ser desfeita.
                    </DialogDescription>
                     <DialogDescription v-else>
                        Tem certeza de que deseja excluir este documento? Esta ação não poderá ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <DialogClose as-child><Button variant="outline" type="button" @click="showDeleteProcessDocumentDialog = false; processDocumentToDelete = null;">Cancelar</Button></DialogClose>
                    <Button variant="destructive" :disabled="processDocumentDeleteForm.processing" @click="submitDeleteProcessDocument">
                        <svg v-if="processDocumentDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ processDocumentDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showTaskDialog" @update:open="showTaskDialog = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingTask ? 'Editar Tarefa' : 'Nova Tarefa para o Caso' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingTask ? 'Modifique os detalhes da tarefa.' : 'Preencha os detalhes da nova tarefa.' }}
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitProcessTask" class="space-y-4 mt-4">
                    <div>
                        <Label for="taskTitle">Título <span class="text-red-500">*</span></Label>
                        <Input id="taskTitle" v-model="taskForm.title" required />
                        <div v-if="taskForm.errors.title" class="text-sm text-red-500 mt-1">{{ taskForm.errors.title }}</div>
                    </div>
                    <div>
                        <Label for="taskDescription">Descrição</Label>
                        <Textarea id="taskDescription" v-model="taskForm.description" rows="3" />
                        <div v-if="taskForm.errors.description" class="text-sm text-red-500 mt-1">{{ taskForm.errors.description }}</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label for="taskDueDate">Data de Vencimento</Label>
                            <Input id="taskDueDate" type="date" v-model="taskForm.due_date" />
                            <div v-if="taskForm.errors.due_date" class="text-sm text-red-500 mt-1">{{ taskForm.errors.due_date }}</div>
                        </div>
                        <div>
                            <Label for="taskResponsible">Responsável</Label>
                            <Select v-model="taskForm.responsible_user_id">
                                <SelectTrigger id="taskResponsible">
                                    <SelectValue placeholder="Selecionar responsável" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Ninguém</SelectItem>
                                    <SelectItem v-for="user in props.users" :key="user.id" :value="String(user.id)">
                                        {{ user.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="taskForm.errors.responsible_user_id" class="text-sm text-red-500 mt-1">{{ taskForm.errors.responsible_user_id }}</div>
                        </div>
                    </div>
                    <div>
                        <Label for="taskStatus">Status da Tarefa</Label>
                        <Select v-model="taskForm.status">
                            <SelectTrigger id="taskStatus">
                                <SelectValue placeholder="Selecionar status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="statusOpt in taskStatusOptions" :key="statusOpt.key" :value="statusOpt.key">
                                    {{ statusOpt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                         <div v-if="taskForm.errors.status" class="text-sm text-red-500 mt-1">{{ taskForm.errors.status }}</div>
                    </div>
                    <DialogFooter class="mt-6">
                        <DialogClose as-child><Button type="button" variant="outline" @click="showTaskDialog = false; taskForm.reset(); editingTask = null; taskForm.clearErrors();">Cancelar</Button></DialogClose>
                        <Button type="submit" :disabled="taskForm.processing">
                            <PlusCircle class="mr-2 h-4 w-4" v-if="!editingTask && !taskForm.processing" />
                            <Edit3 class="mr-2 h-4 w-4" v-if="editingTask && !taskForm.processing" />
                            <svg v-if="taskForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ taskForm.processing ? 'Salvando...' : (editingTask ? 'Salvar Alterações' : 'Salvar Tarefa') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDeleteTaskDialog" @update:open="showDeleteTaskDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Tarefa</DialogTitle>
                    <DialogDescription v-if="taskToDelete">
                        Tem certeza de que deseja excluir a tarefa <strong class="font-medium">"{{ taskToDelete.title }}"</strong>?
                        Esta ação não poderá ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showDeleteTaskDialog = false; taskToDelete = null;">Cancelar</Button>
                    <Button variant="destructive" @click="submitDeleteTask" :disabled="taskDeleteForm.processing">
                        <svg v-if="taskDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ taskDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

         <Dialog :open="showAddFeeDialog" @update:open="showAddFeeDialog = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Adicionar Honorários ao Caso</DialogTitle>
                    <DialogDescription>
                        Preencha os detalhes dos honorários.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitFee" class="space-y-4 mt-4 max-h-[70vh] overflow-y-auto pr-2">
                    <div>
                        <Label for="feeDescription">Descrição do Serviço/Honorário <span class="text-red-500">*</span></Label>
                        <Input id="feeDescription" v-model="feeForm.description" required />
                        <div v-if="feeForm.errors.description" class="text-sm text-red-500 mt-1">{{ feeForm.errors.description }}</div>
                    </div>

                    <div>
                        <Label for="feeAmount">Valor Total do Honorário (R$) <span class="text-red-500">*</span></Label>
                        <Input id="feeAmount" type="number" step="0.01" min="0.01" v-model="feeForm.amount" required />
                        <div v-if="feeForm.errors.amount" class="text-sm text-red-500 mt-1">{{ feeForm.errors.amount }}</div>
                    </div>

                    <div class="flex items-center space-x-2 mt-4">
                        <input type="checkbox" id="is_fee_installment" v-model="feeForm.is_installment" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600" />
                        <Label for="is_fee_installment" class="text-sm font-medium">Parcelar este honorário?</Label>
                    </div>
                     <div v-if="feeForm.errors.is_installment" class="text-sm text-red-500 mt-1">{{ feeForm.errors.is_installment }}</div>

                    <template v-if="feeForm.is_installment">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 border-t pt-4 dark:border-gray-700">
                            <div>
                                <Label for="fee_number_of_installments">Número de Parcelas <span class="text-red-500">*</span></Label>
                                <Input id="fee_number_of_installments" type="number" min="2" v-model="feeForm.number_of_installments" required />
                                <div v-if="feeForm.errors.number_of_installments" class="text-sm text-red-500 mt-1">{{ feeForm.errors.number_of_installments }}</div>
                            </div>
                            <div>
                                <Label for="fee_first_installment_date">Data da 1ª Parcela <span class="text-red-500">*</span></Label>
                                <Input id="fee_first_installment_date" type="date" v-model="feeForm.first_installment_date" required />
                                <div v-if="feeForm.errors.first_installment_date" class="text-sm text-red-500 mt-1">{{ feeForm.errors.first_installment_date }}</div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400" v-if="feeForm.amount && feeForm.number_of_installments && parseFloat(String(feeForm.number_of_installments)) > 0">
                            Serão geradas {{ feeForm.number_of_installments }} parcelas de R$ {{ (parseFloat(String(feeForm.amount)) / parseFloat(String(feeForm.number_of_installments))).toFixed(2) }}.
                        </p>
                    </template>
                    <template v-else>
                         <div class="mt-4">
                            <Label for="fee_due_date_single">Data de Vencimento (Pagamento Único) <span class="text-red-500">*</span></Label>
                            <Input id="fee_due_date_single" type="date" v-model="feeForm.first_installment_date" required />
                            <div v-if="feeForm.errors.first_installment_date" class="text-sm text-red-500 mt-1">{{ feeForm.errors.first_installment_date }}</div>
                        </div>
                    </template>

                    <div class="mt-4 border-t pt-4 dark:border-gray-700">
                        <Label class="text-base font-medium text-gray-700 dark:text-gray-300">
                            {{ feeForm.is_installment ? 'Pagamento da 1ª Parcela/Entrada (Opcional)' : 'Detalhes do Pagamento (Opcional)'}}
                        </Label>
                        <div class="flex items-center space-x-2 mt-4">
                            <input type="checkbox" id="is_first_payment_paid" v-model="feeForm.is_first_payment_paid" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600" />
                            <Label for="is_first_payment_paid" class="text-sm font-medium">
                                {{ feeForm.is_installment ? '1ª Parcela/Entrada já foi paga?' : 'Honorário já foi pago?' }}
                            </Label>
                        </div>
                         <div v-if="feeForm.errors.is_first_payment_paid" class="text-sm text-red-500 mt-1">{{ feeForm.errors.is_first_payment_paid }}</div>

                        <div v-if="feeForm.is_first_payment_paid" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                            <div>
                                <Label for="fee_actual_payment_date">Data do Pagamento <span class="text-red-500">*</span></Label>
                                <Input id="fee_actual_payment_date" type="date" v-model="feeForm.actual_payment_date" :required="feeForm.is_first_payment_paid" />
                                <div v-if="feeForm.errors.actual_payment_date" class="text-sm text-red-500 mt-1">{{ feeForm.errors.actual_payment_date }}</div>
                            </div>
                            <div>
                                <Label for="fee_payment_method_single">Forma de Pagamento</Label>
                                <Select v-model="feeForm.payment_method">
                                    <SelectTrigger id="fee_payment_method_single">
                                        <SelectValue placeholder="Selecione a forma" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Formas de Pagamento</SelectLabel>
                                            <SelectItem :value="null">Não especificado</SelectItem>
                                            <SelectItem v-for="method in props.paymentMethods" :key="method" :value="method">
                                                {{ method }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <div v-if="feeForm.errors.payment_method" class="text-sm text-red-500 mt-1">{{ feeForm.errors.payment_method }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <Label for="feeNotesOverall">Observações Gerais</Label>
                        <Textarea id="feeNotesOverall" v-model="feeForm.notes" rows="3" />
                        <div v-if="feeForm.errors.notes" class="text-sm text-red-500 mt-1">{{ feeForm.errors.notes }}</div>
                    </div>

                    <DialogFooter class="mt-6">
                        <DialogClose as-child><Button type="button" variant="outline" @click="showAddFeeDialog = false; feeForm.reset(); feeForm.clearErrors();">Cancelar</Button></DialogClose>
                        <Button type="submit" :disabled="feeForm.processing">
                            <HandCoins class="mr-2 h-4 w-4" v-if="!feeForm.processing" />
                            <svg v-if="feeForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ feeForm.processing ? 'Salvando...' : 'Salvar Honorários' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showEditFeeDialog" @update:open="showEditFeeDialog = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar Honorários</DialogTitle>
                    <DialogDescription>
                        Modifique os detalhes dos honorários. ID: {{ editingFee?.id }}
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitEditFee" class="space-y-4 mt-4">
                    <div>
                        <Label for="editFeeDescription">Descrição <span class="text-red-500">*</span></Label>
                        <Input id="editFeeDescription" v-model="editFeeForm.description" required />
                        <div v-if="editFeeForm.errors.description" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.description }}</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label for="editFeeAmount">Valor (R$) <span class="text-red-500">*</span></Label>
                            <Input id="editFeeAmount" type="number" step="0.01" min="0.01" v-model="editFeeForm.amount" required />
                            <div v-if="editFeeForm.errors.amount" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.amount }}</div>
                        </div>
                        <div>
                            <Label for="editFeeDate">Data de Vencimento <span class="text-red-500">*</span></Label>
                            <Input id="editFeeDate" type="date" v-model="editFeeForm.fee_date" required />
                            <div v-if="editFeeForm.errors.fee_date" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.fee_date }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label for="editFeePaymentMethod">Forma de Pagamento</Label>
                            <Select v-model="editFeeForm.payment_method">
                                <SelectTrigger id="editFeePaymentMethod">
                                    <SelectValue placeholder="Selecione a forma" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Formas de Pagamento</SelectLabel>
                                        <SelectItem :value="null">Não especificado</SelectItem>
                                        <SelectItem v-for="method in props.paymentMethods" :key="method" :value="method">
                                            {{ method }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <div v-if="editFeeForm.errors.payment_method" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.payment_method }}</div>
                        </div>
                         <div v-if="editFeeForm.is_paid">
                            <Label for="editFeeActualPaymentDate">Data de Pagamento <span class="text-red-500">*</span></Label>
                            <Input id="editFeeActualPaymentDate" type="date" v-model="editFeeForm.payment_date" :required="editFeeForm.is_paid" />
                            <div v-if="editFeeForm.errors.payment_date" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.payment_date }}</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 mt-4">
                        <input type="checkbox" id="is_paid_edit_fee" v-model="editFeeForm.is_paid" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600" />
                        <Label for="is_paid_edit_fee" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                            Marcar como Pago?
                        </Label>
                    </div>
                    <div v-if="editFeeForm.errors.is_paid" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.is_paid }}</div>

                    <div>
                        <Label for="editFeeNotes">Observações Adicionais</Label>
                        <Textarea id="editFeeNotes" v-model="editFeeForm.notes" rows="3" />
                        <div v-if="editFeeForm.errors.notes" class="text-sm text-red-500 mt-1">{{ editFeeForm.errors.notes }}</div>
                    </div>
                    <DialogFooter class="mt-6">
                        <DialogClose as-child><Button type="button" variant="outline" @click="showEditFeeDialog = false; editFeeForm.reset(); editingFee = null; editFeeForm.clearErrors();">Cancelar</Button></DialogClose>
                        <Button type="submit" :disabled="editFeeForm.processing">
                            <Edit3 class="mr-2 h-4 w-4" v-if="!editFeeForm.processing" />
                            <svg v-if="editFeeForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ editFeeForm.processing ? 'Salvando...' : 'Salvar Alterações' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showEditPaymentDialog" @update:open="showEditPaymentDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar Pagamento</DialogTitle>
                    <DialogDescription v-if="editingPayment">
                        <p class="mb-2">Atualize o status e a data de pagamento para:</p>
                        <p class="text-sm"><strong>ID:</strong> {{ editingPayment.id }}</p>
                        <p class="text-sm"><strong>Valor Principal:</strong> {{ formatCurrency(editingPayment.total_amount) }}</p>
                        <p class="text-sm"><strong>Vencimento:</strong> {{ formatDate(editingPayment.first_installment_due_date || editingPayment.down_payment_date) }}</p>
                        <div v-if="editingPayment.notes" class="text-sm break-words">
                            <strong>Notas:</strong>
                            <span>
                                {{ (isNoteInDialogExpanded || editingPayment.notes.length <= 80) ? editingPayment.notes : `${editingPayment.notes.substring(0, 60)}...` }}
                            </span>
                        </div>
                         <p v-else class="text-sm"><strong>Notas:</strong> N/A</p>

                        <p v-if="editingPayment.interest_amount && parseFloat(String(editingPayment.interest_amount)) > 0" class="text-sm text-red-500 dark:text-red-400">
                            <strong>Juros Atuais:</strong> {{ formatCurrency(editingPayment.interest_amount) }}
                        </p>
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitEditPayment" class="space-y-4 mt-4">
                    <div>
                        <Label for="editPaymentStatus" class="text-sm">Status do Pagamento <span class="text-red-500">*</span></Label>
                        <Select v-model="editPaymentForm.status" required>
                            <SelectTrigger id="editPaymentStatus">
                                <SelectValue placeholder="Selecione o status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Status</SelectLabel>
                                    <SelectItem v-for="statusOpt in props.paymentStatuses" :key="statusOpt.key" :value="statusOpt.key">
                                        {{ statusOpt.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <div v-if="editPaymentForm.errors.status" class="text-sm text-red-500 mt-1">{{ editPaymentForm.errors.status }}</div>
                    </div>

                    <div v-if="editPaymentForm.status === 'paid'">
                        <Label for="editPaymentActualDate">Data de Pagamento Efetivo <span class="text-red-500">*</span></Label>
                        <Input id="editPaymentActualDate" type="date" v-model="editPaymentForm.payment_date" :required="editPaymentForm.status === 'paid'" />
                        <div v-if="editPaymentForm.errors.payment_date" class="text-sm text-red-500 mt-1">{{ editPaymentForm.errors.payment_date }}</div>
                    </div>

                    <div v-if="showInterestFieldForEditPayment">
                        <Label for="editPaymentInterestAmount" class="text-sm">Valor dos Juros (R$)</Label>
                        <Input id="editPaymentInterestAmount" type="number" step="0.01" min="0" v-model="editPaymentForm.interest_amount" placeholder="Ex: 15.50" />
                        <div v-if="editPaymentForm.errors.interest_amount" class="text-sm text-red-500 mt-1">{{ editPaymentForm.errors.interest_amount }}</div>
                    </div>

                    <DialogFooter class="mt-6">
                        <DialogClose as-child><Button type="button" variant="outline" @click="showEditPaymentDialog = false; editPaymentForm.reset(); editingPayment = null; editPaymentForm.clearErrors();">Cancelar</Button></DialogClose>
                        <Button type="submit" :disabled="editPaymentForm.processing">
                            <Edit3 class="mr-2 h-4 w-4" v-if="!editPaymentForm.processing" />
                            <svg v-if="editPaymentForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ editPaymentForm.processing ? 'Salvando...' : 'Salvar Alterações' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
