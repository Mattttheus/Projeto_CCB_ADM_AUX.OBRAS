// Presentation/Pages — equivalente a page/upload_doc.php
// No modo Supabase o arquivo vai para o bucket "documentos" do Storage;
// no modo demonstração apenas o registro local é mantido.
import { layout, panel, badge, formatDate, escapeHtml } from '../ui.js';

export function render(ctx) {
    const obraIds = new Set(ctx.store.obrasFor(ctx.auth.user()).map(obra => obra.id));
    const docs = ctx.store.state.documents.filter(item => obraIds.has(item.obraId) || item.obraId == null);
    return layout('Documentos', 'Centralize comprovantes, plantas e arquivos da obra.',
        '<button class="button button-primary" data-action="new-document">+ Adicionar arquivo</button>')
        + panel('Arquivos recentes', `<div class="data-list">${docs.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.name)}</div>
                <div class="row-meta">${escapeHtml(ctx.store.obraName(item.obraId))} · Adicionado em ${formatDate(item.date)}</div></div>
                ${badge('informativo', item.type ?? 'Geral')}
                ${item.path ? `<button class="table-action" data-download="${item.id}">Baixar</button>` : ''}
                <button class="table-action" data-delete="document:${item.id}">Excluir</button>
            </div>`).join('') || '<div class="empty-state">Nenhum documento adicionado.</div>'}</div>`);
}

export function bind() { /* upload e download tratados pela delegação global do app.js */ }
