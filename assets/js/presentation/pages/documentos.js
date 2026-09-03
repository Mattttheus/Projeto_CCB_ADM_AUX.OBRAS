// Presentation/Pages — equivalente a page/upload_doc.php (somente registro local no modo estático)
import { layout, panel, formatDate, escapeHtml } from '../ui.js';

export function render(ctx) {
    const docs = ctx.store.state.documents;
    return layout('Documentos', 'Centralize comprovantes, plantas e arquivos da obra.',
        '<label class="button button-primary" for="document-file">+ Adicionar arquivo</label><input id="document-file" type="file" class="hidden">')
        + panel('Arquivos recentes', `<div class="data-list">${docs.map(item => `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${escapeHtml(item.name)}</div>
                <div class="row-meta">Adicionado em ${formatDate(item.date)}</div></div>
                <button class="table-action" data-delete="document:${item.id}">Excluir</button>
            </div>`).join('') || '<div class="empty-state">Nenhum documento adicionado.</div>'}</div>`);
}

export function bind(ctx) {
    document.querySelector('#document-file').addEventListener('change', event => {
        const file = event.target.files[0];
        if (!file) return;
        ctx.store.state.documents.unshift({
            id: ctx.store.nextId(ctx.store.state.documents),
            name: file.name,
            date: new Date().toISOString().slice(0, 10),
        });
        ctx.store.log(`Documento "${file.name}" registrado localmente.`);
        ctx.store.save();
        ctx.rerender();
        ctx.notify('Arquivo registrado localmente.');
    });
}
