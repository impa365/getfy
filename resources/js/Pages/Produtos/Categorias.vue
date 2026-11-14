<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import ProdutosTabs from '@/components/produtos/ProdutosTabs.vue';
import { Lock, Pencil, Tags, Trash2, Check, X } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

defineProps({
    categorias: { type: Array, default: () => [] },
});

const CORES = ['#0ea5e9', '#22c55e', '#a3e635', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#64748b'];

const nova = useForm({ name: '', color: CORES[0] });
const editando = ref(null);
const edicao = useForm({ name: '', color: '' });
const paraExcluir = ref(null);

function criar() {
    nova.post('/produtos/categorias', {
        preserveScroll: true,
        onSuccess: () => nova.reset('name'),
    });
}

function editar(c) {
    editando.value = c.id;
    edicao.name = c.name;
    edicao.color = c.color || CORES[0];
    edicao.clearErrors();
}

function salvar(c) {
    edicao.put(`/produtos/categorias/${c.id}`, {
        preserveScroll: true,
        onSuccess: () => { editando.value = null; },
    });
}

function excluir() {
    const c = paraExcluir.value;
    if (!c) return;
    router.delete(`/produtos/categorias/${c.id}`, { preserveScroll: true });
    paraExcluir.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <ProdutosTabs />

        <form class="panel-card flex flex-col gap-3 p-4 sm:flex-row sm:items-end" @submit.prevent="criar">
            <div class="flex-1">
                <label for="nova-categoria" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nova categoria</label>
                <input
                    id="nova-categoria"
                    v-model="nova.name"
                    type="text"
                    maxlength="80"
                    placeholder="Ex.: Cursos, Streaming, Serviços"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                />
                <p v-if="nova.errors.name" class="mt-1 text-xs text-red-500">{{ nova.errors.name }}</p>
            </div>
            <div>
                <span class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cor</span>
                <div class="flex items-center gap-1.5">
                    <button
                        v-for="cor in CORES"
                        :key="cor"
                        type="button"
                        :aria-label="`Cor ${cor}`"
                        :class="['h-7 w-7 rounded-full border-2 transition', nova.color === cor ? 'border-zinc-900 dark:border-white' : 'border-transparent']"
                        :style="{ backgroundColor: cor }"
                        @click="nova.color = cor"
                    />
                </div>
            </div>
            <Button type="submit" :disabled="nova.processing || !nova.name.trim()">Criar categoria</Button>
        </form>

        <div v-if="categorias.length" class="panel-table">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Categoria</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Produtos</th>
                            <th scope="col" class="relative px-4 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <tr v-for="c in categorias" :key="c.id" class="bg-white dark:bg-zinc-800">
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-white">
                                <div v-if="editando === c.id" class="flex flex-wrap items-center gap-2">
                                    <input
                                        v-model="edicao.name"
                                        type="text"
                                        maxlength="80"
                                        class="w-56 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                        @keydown.enter.prevent="salvar(c)"
                                    />
                                    <button
                                        v-for="cor in CORES"
                                        :key="cor"
                                        type="button"
                                        :aria-label="`Cor ${cor}`"
                                        :class="['h-5 w-5 rounded-full border-2', edicao.color === cor ? 'border-zinc-900 dark:border-white' : 'border-transparent']"
                                        :style="{ backgroundColor: cor }"
                                        @click="edicao.color = cor"
                                    />
                                    <p v-if="edicao.errors.name" class="w-full text-xs text-red-500">{{ edicao.errors.name }}</p>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: c.color || '#a1a1aa' }" aria-hidden="true" />
                                    <span class="font-medium">{{ c.name }}</span>
                                    <span
                                        v-if="c.system"
                                        class="inline-flex items-center gap-1 rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400"
                                        title="Mantida automaticamente por um plugin"
                                    >
                                        <Lock class="h-3 w-3" /> plugin
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <Link :href="`/produtos?categoria=${c.id}`" class="text-zinc-600 hover:text-[var(--color-primary)] dark:text-zinc-400">
                                    {{ c.products_count }} {{ c.products_count === 1 ? 'produto' : 'produtos' }}
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div v-if="!c.system" class="flex justify-end gap-1">
                                    <template v-if="editando === c.id">
                                        <button type="button" class="rounded-lg p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30" aria-label="Salvar" @click="salvar(c)">
                                            <Check class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700" aria-label="Cancelar" @click="editando = null">
                                            <X class="h-4 w-4" />
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button type="button" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200" aria-label="Editar" @click="editar(c)">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" aria-label="Excluir" @click="paraExcluir = c">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else class="panel-card-dashed flex flex-col items-center justify-center py-16 text-center">
            <Tags class="h-14 w-14 text-zinc-400 dark:text-zinc-500" />
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Nenhuma categoria ainda.</p>
            <p class="mt-1 max-w-md text-sm text-zinc-500 dark:text-zinc-400">
                Crie categorias para organizar e filtrar seus produtos. Cada produto pode estar em mais de uma, e você escolhe na aba Geral do produto.
            </p>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="paraExcluir" class="fixed inset-0 z-[100002] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70" aria-hidden="true" @click="paraExcluir = null" />
            <div class="relative w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Excluir categoria?</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    A categoria <strong class="text-zinc-900 dark:text-white">"{{ paraExcluir.name }}"</strong> será excluída.
                    Os produtos não são apagados, só deixam de estar nela.
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <Button variant="outline" @click="paraExcluir = null">Cancelar</Button>
                    <Button variant="destructive" @click="excluir">Excluir</Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
