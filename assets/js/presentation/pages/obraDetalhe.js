// Presentation/Pages — página dedicada de UMA obra, endereçável via #obra?id=<id>.
// Equivalente ao page/gerenciar_obra.php (que também usa um parâmetro obra_id):
// seletor de obra, cronograma com status editável, manutenção recorrente,
// financeiro, documentos com busca e chamados com farol de prioridade —
// tudo numa página só, com atalhos de cadastro já pré-selecionando a obra.
import { obraStatusLabel } from '../../domain/obra/ObraStatus.js';
import { displayStatus, ACTIVITY_STATUS_LABELS, allStatuses, WEEKDAY_LABELS } from '../../domain/activity/ActivityStatus.js';
import { categoryLabel } from '../../domain/finance/FinancialCategory.js';
import { layout, panel, badge, money, formatDate, formatDateTime, escapeHtml } from '../ui.js';

const FAROL = { verde: '🟢', amarelo: '🟡', vermelho: '🔴' };

/** Estado da aba de filtro do farol de chamados — como currentTab em calendario.js. */
let chamadoFiltro = 'todos';

function currentObraId() {
    return Number(new URLSearchParams(location.hash.split('?')[1] ?? '').get('id'));
}

/** Só obras que o usuário pode acessar (RLS já filtra no Supabase; obrasFor() filtra no modo demo). */
function currentObra(ctx) {
    return ctx.store.obrasFor(ctx.auth.user()).find(item => item.id === currentObraId());
}

function statusSelect(item) {
    const shown = displayStatus(item);
    return `<select class="field status-select" data-status-select="${item.id}">
        ${allStatuses().map(key => `<option value="${key}" ${item.status === key ? 'selected' : ''}>${ACTIVITY_STATUS_LABELS[key]}</option>`).join('')}
    </select>${shown === 'atrasada' ? badge('atrasada', ACTIVITY_STATUS_LABELS.atrasada) : ''}`;
}

export function render(ctx) {
    const obra = currentObra(ctx);
    if (!obra) {
        return layout('Obra não encontrada', 'Ela pode ter sido excluída, ou você não tem acesso a ela.',
            '<button class="button button-light" data-route-link="obras">← Voltar para Obras</button>');
    }

    const obraId = obra.id;
    const obrasDisponiveis = ctx.store.obrasFor(ctx.auth.user());
    const activities = ctx.store.state.activities.filter(item => item.obraId === obraId);
    const cronograma = activities.filter(item => item.type !== 'recorrente')
        .sort((a, b) => (a.date ?? '').localeCompare(b.date ?? ''));
    const manutencoes = activities.filter(item => item.type === 'recorrente')
        .sort((a, b) => (a.dayOfWeek ?? 0) - (b.dayOfWeek ?? 0));
    const transactions = ctx.store.state.transactions.filter(item => item.obraId === obraId)
        .sort((a, b) => b.date.localeCompare(a.date));
    const documents = ctx.store.state.documents.filter(item => item.obraId === obraId)
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));
    const chamadosObra = ctx.store.state.chamados.filter(item => item.obraId === obraId)
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));

    const farolCounts = { verde: 0, amarelo: 0, vermelho: 0 };
    chamadosObra.forEach(item => { if (item.status !== 'fechado' && farolCounts[item.priority] !== undefined) farolCounts[item.priority] += 1; });
    const chamados = chamadoFiltro === 'todos' ? chamadosObra : chamadosObra.filter(item => item.priority === chamadoFiltro);

    const spent = transactions.reduce((sum, item) => sum + item.quantity * item.unitCost, 0);
    const progress = ctx.store.obraProgress(obraId);

    return layout(obra.name, escapeHtml(obra.city || 'Detalhes, cronograma e manutenção desta obra.'),
        '<button class="button button-light" data-route-link="obras">← Voltar para Obras</button>')
        + panel('Visão geral', `
            <div class="toolbar">
                <select class="field" id="obra-switch" aria-label="Trocar de obra">
                    ${obrasDisponiveis.map(item => `<option value="${item.id}" ${item.id === obraId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`).join('')}
                </select>
            </div>
            <div class="list-row">
                <div class="row-main">
                    <div class="row-title">${badge(obra.status, obraStatusLabel(obra.status))} Progresso do cronograma: <strong>${progress}%</strong></div>
                    <div class="row-meta">Orçamento: <strong>${money(obra.budget)}</strong> · Gasto: <strong>${money(spent)}</strong> · Saldo: <strong>${money(obra.budget - spent)}</strong></div>
                </div>
            </div>`)
        + panel('Gestão da obra', `
            <h3 class="detail-heading">Cronograma <button class="table-action" data-action="new-activity" data-preset-obra="${obraId}">+ Atividade</button></h3>
            <div class="data-list">${cronograma.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                    <div class="row-meta">Prazo ${formatDate(item.date)}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div></div>
                    ${statusSelect(item)}
                    <button class="table-action" data-delete="activity:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhuma atividade cadastrada.</div>'}</div>

            <h3 class="detail-heading">Manutenção recorrente <button class="table-action" data-action="new-maintenance" data-preset-obra="${obraId}">+ Manutenção</button></h3>
            <div class="data-list">${manutencoes.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                    <div class="row-meta">Toda ${WEEKDAY_LABELS[item.dayOfWeek] ?? '—'}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div></div>
                    ${statusSelect(item)}
                    <button class="table-action" data-delete="activity:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhuma manutenção recorrente cadastrada.</div>'}</div>

            <h3 class="detail-heading">Financeiro <button class="table-action" data-action="new-transaction" data-preset-obra="${obraId}">+ Lançamento</button></h3>
            <div class="data-list">${transactions.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.description)}</div>
                    <div class="row-meta">${escapeHtml(categoryLabel(item.category))} · ${formatDate(item.date)} · ${item.quantity} × ${money(item.unitCost)}</div></div>
                    <strong>${money(item.quantity * item.unitCost)}</strong>
                    <button class="table-action" data-delete="transaction:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhum lançamento nesta obra.</div>'}</div>

            <h3 class="detail-heading">Documentos (${documents.length}) <button class="table-action" data-action="new-document" data-preset-obra="${obraId}">+ Arquivo</button></h3>
            <div class="toolbar"><input class="field" id="doc-filter" placeholder="Filtrar por nome do arquivo" aria-label="Filtrar documentos"></div>
            <div class="data-list" id="doc-list">${documents.map(item => `
                <div class="list-row" data-doc-name="${escapeHtml(item.name.toLowerCase())}">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.name)}</div>
                    <div class="row-meta">${escapeHtml(item.type ?? 'Geral')} · Adicionado em ${formatDate(item.date)}</div></div>
                    ${item.path ? `<button class="table-action" data-download="${item.id}">Baixar</button>` : ''}
                    <button class="table-action" data-delete="document:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhum documento nesta obra.</div>'}</div>

            <h3 class="detail-heading">Chamados (farol)</h3>
            <div class="tab-bar" id="farol-tabs" role="tablist">
                <button class="tab ${chamadoFiltro === 'todos' ? 'active' : ''}" data-chamado-filter="todos">Todos</button>
                <button class="tab ${chamadoFiltro === 'vermelho' ? 'active' : ''}" data-chamado-filter="vermelho">🔴 ${farolCounts.vermelho}</button>
                <button class="tab ${chamadoFiltro === 'amarelo' ? 'active' : ''}" data-chamado-filter="amarelo">🟡 ${farolCounts.amarelo}</button>
                <button class="tab ${chamadoFiltro === 'verde' ? 'active' : ''}" data-chamado-filter="verde">🟢 ${farolCounts.verde}</button>
            </div>
            <div class="data-list">${chamados.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${FAROL[item.priority] ?? ''} ${escapeHtml(item.title)}</div>
                    <div class="row-meta">${escapeHtml(item.description ?? '')}</div>
                    ${item.date ? `<div class="row-meta">Aberto em ${formatDateTime(item.date)}</div>` : ''}</div>
                    ${badge(item.status)}
                    ${item.status !== 'fechado' ? `<button class="table-action" data-chamado-status="${item.id}:fechado">Fechar</button>` : ''}
                </div>`).join('') || '<div class="empty-state">Nenhum chamado com este filtro.</div>'}</div>`);
}

export function bind(ctx) {
    document.querySelector('#obra-switch')?.addEventListener('change', event => {
        location.hash = `obra?id=${event.target.value}`;
    });

    document.querySelector('#doc-filter')?.addEventListener('input', event => {
        const term = event.target.value.trim().toLowerCase();
        document.querySelectorAll('#doc-list [data-doc-name]').forEach(row => {
            row.style.display = row.dataset.docName.includes(term) ? '' : 'none';
        });
    });

    document.querySelector('#farol-tabs')?.addEventListener('click', event => {
        const filtro = event.target.closest('[data-chamado-filter]')?.dataset.chamadoFilter;
        if (filtro && filtro !== chamadoFiltro) {
            chamadoFiltro = filtro;
            ctx.rerender();
        }
    });
}

// Cadastro/exclusão/download/avanço de status são tratados pela delegação global
// do app.js (new-activity/new-maintenance/new-transaction/new-document, data-delete,
// data-download, data-status-select, data-chamado-status) — funcionam aqui como nas outras páginas.
