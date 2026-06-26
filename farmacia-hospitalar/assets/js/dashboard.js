// assets/js/dashboard.js
// Conecta o visual do dashboard com o banco de dados real
// através do controllers/requisicao_controller.php (respostas em JSON).

let requisicoesAtuais = [];
let idRequisicaoAberta = null;

document.addEventListener('DOMContentLoaded', function () {
    carregarRequisicoes();
    document.getElementById('search-input').addEventListener('keyup', filtrarRequisicoes);
    document.getElementById('filter-status').addEventListener('change', filtrarRequisicoes);

    const formNovaReq = document.getElementById('new-req-form');
    if (formNovaReq) {
        formNovaReq.addEventListener('submit', criarRequisicao);
        adicionarLinhaMedicamento();
    }

    const dateInput = document.getElementById('form-data');
    if (dateInput) {
        const agora = new Date();
        agora.setMinutes(agora.getMinutes() - agora.getTimezoneOffset());
        dateInput.value = agora.toISOString().slice(0, 16);
    }
});

/**
 * Busca a lista de requisições e estatísticas no banco via AJAX.
 */
async function carregarRequisicoes() {
    try {
        const resposta = await fetch('../controllers/requisicao_controller.php?action=listar');
        const dados = await resposta.json();
        requisicoesAtuais = dados.requisicoes || [];
        atualizarEstatisticas(dados.estatisticas);
        renderizarTabela(requisicoesAtuais);
    } catch (erro) {
        mostrarToast('Erro ao carregar requisições do banco.', 'error');
        console.error(erro);
    }
}

function atualizarEstatisticas(stats) {
    if (!stats) return;
    document.getElementById('stat-total').innerText = stats.total;
    document.getElementById('stat-pending').innerText = stats.pendente;
    document.getElementById('stat-served').innerText = stats.atendido;
    document.getElementById('stat-urgent').innerText = stats.urgente;
}

function formatarDataBR(dataStr) {
    if (!dataStr) return '-';
    const data = new Date(dataStr.replace(' ', 'T'));
    if (isNaN(data)) return dataStr;
    return data.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/**
 * Desenha as linhas da tabela de requisições com base na lista filtrada.
 */
function renderizarTabela(lista) {
    const tbody = document.getElementById('requisitions-list');
    const emptyState = document.getElementById('empty-state');
    tbody.innerHTML = '';

    document.getElementById('showing-count').innerText = `${lista.length} listadas`;

    if (lista.length === 0) {
        emptyState.classList.remove('hidden');
        return;
    }
    emptyState.classList.add('hidden');

    lista.forEach(req => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/70 transition duration-150';

        const pClass = req.prioridade === 'Urgente' ? 'bg-red-50 text-red-700 border-red-100'
            : req.prioridade === 'Alta' ? 'bg-amber-50 text-amber-700 border-amber-100'
            : 'bg-emerald-50 text-emerald-700 border-emerald-100';

        const sClass = req.status === 'Pendente' ? 'bg-blue-100 text-blue-800 border-blue-200'
            : req.status === 'Atendido' ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
            : 'bg-slate-100 text-slate-500 border-slate-200';

        const itensResumo = (req.itens || []).map(i => `${i.quantidade_solicitada}x ${i.nome.split(' ')[0]}`).join(', ');

        const botoes = req.status === 'Pendente' ? `
            <div class="flex items-center justify-center space-x-2">
                <button onclick="abrirModal(${req.id_requisicao})" class="px-3 py-1.5 bg-teal-600 text-white rounded-lg text-xs font-semibold hover:bg-teal-700 transition flex items-center space-x-1 shadow-sm"><i class="fa-solid fa-boxes-packing"></i><span>Atender</span></button>
                <button onclick="cancelarRequisicao(${req.id_requisicao})" class="p-1.5 bg-slate-100 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg text-xs transition border border-slate-200"><i class="fa-solid fa-trash"></i></button>
            </div>` : `
            <div class="flex items-center justify-center">
                <button onclick="abrirModal(${req.id_requisicao})" class="px-3 py-1.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-semibold hover:bg-slate-200 transition flex items-center space-x-1"><i class="fa-solid fa-eye"></i><span>Ver Detalhes</span></button>
            </div>`;

        tr.innerHTML = `
            <td class="px-6 py-4">
                <span class="text-xs text-slate-400 block font-mono">#REQ-${req.id_requisicao}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border ${pClass} mt-1">${req.prioridade}</span>
            </td>
            <td class="px-6 py-4 font-semibold text-slate-700">${req.nome_paciente || '-'}</td>
            <td class="px-6 py-4"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-medium border border-slate-200">${req.setor_origem || req.sigla_origem}</span></td>
            <td class="px-6 py-4 text-xs text-slate-500">${formatarDataBR(req.criado_em)}</td>
            <td class="px-6 py-4">
                <span class="text-xs font-medium text-slate-700 block">${(req.itens || []).length} item(ns)</span>
                <span class="text-[11px] text-slate-400 block max-w-xs truncate">${itensResumo}</span>
            </td>
            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-semibold border ${sClass}">${req.status}</span></td>
            <td class="px-6 py-4 text-center">${botoes}</td>
        `;
        tbody.appendChild(tr);
    });
}

function filtrarRequisicoes() {
    const termo = document.getElementById('search-input').value.toLowerCase();
    const statusFiltro = document.getElementById('filter-status').value;

    const filtradas = requisicoesAtuais.filter(req => {
        const condStatus = statusFiltro === 'Todos' ? true : req.status === statusFiltro;
        const condBusca = (req.nome_paciente || '').toLowerCase().includes(termo)
            || String(req.id_requisicao).includes(termo)
            || (req.itens || []).some(i => i.nome.toLowerCase().includes(termo));
        return condStatus && condBusca;
    });

    renderizarTabela(filtradas);
}

/**
 * Cancela uma requisição pendente direto no banco.
 */
async function cancelarRequisicao(idRequisicao) {
    if (!confirm('Tem certeza que deseja cancelar esta requisição?')) return;

    try {
        const resposta = await fetch('../controllers/requisicao_controller.php?action=cancelar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_requisicao: idRequisicao }),
        });
        const dados = await resposta.json();
        if (dados.sucesso) {
            mostrarToast(`Requisição #REQ-${idRequisicao} cancelada.`, 'error');
            carregarRequisicoes();
        } else {
            mostrarToast(dados.erro || 'Não foi possível cancelar.', 'error');
        }
    } catch (erro) {
        mostrarToast('Erro de conexão ao cancelar.', 'error');
    }
}

/**
 * Abre o modal de detalhe/atendimento buscando os dados reais no banco.
 */
async function abrirModal(idRequisicao) {
    try {
        const resposta = await fetch(`../controllers/requisicao_controller.php?action=detalhe&id=${idRequisicao}`);
        const req = await resposta.json();

        idRequisicaoAberta = idRequisicao;

        document.getElementById('modal-id').innerText = `#REQ-${req.id_requisicao}`;
        document.getElementById('modal-title').innerText = req.nome_paciente || 'Requisição sem paciente vinculado';
        document.getElementById('modal-subtitle').innerText = `Setor: ${req.setor_origem || req.sigla_origem}`;
        document.getElementById('modal-date').innerText = formatarDataBR(req.criado_em);

        const prioridadeBadge = document.getElementById('modal-priority-badge');
        prioridadeBadge.innerText = req.prioridade;
        prioridadeBadge.className = `px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
            req.prioridade === 'Urgente' ? 'bg-red-100 text-red-800' :
            req.prioridade === 'Alta' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`;

        const statusBadge = document.getElementById('modal-status-badge');
        statusBadge.innerText = req.status;
        statusBadge.className = `px-2.5 py-1 rounded-full text-xs font-semibold inline-block mt-0.5 ${
            req.status === 'Pendente' ? 'bg-blue-100 text-blue-800' :
            req.status === 'Atendido' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'}`;

        const tbody = document.getElementById('modal-items-tbody');
        tbody.innerHTML = '';
        (req.itens || []).forEach(item => {
            const tr = document.createElement('tr');
            const campoLote = req.status === 'Pendente'
                ? `<input type="text" data-id-item="${item.id_item}" placeholder="Ex: LOT-001" class="campo-lote w-full px-2 py-1 rounded border border-slate-300 focus:ring-1 focus:ring-teal-500 text-xs font-mono">`
                : `<span class="font-mono text-xs text-slate-600">${item.numero_lote_dispensado || 'Sem lote'}</span>`;

            tr.innerHTML = `
                <td class="px-4 py-3 font-semibold text-slate-700">${item.nome}</td>
                <td class="px-4 py-3 text-center text-slate-900 font-bold bg-slate-50">${item.quantidade_solicitada}</td>
                <td class="px-4 py-3">${campoLote}</td>
            `;
            tbody.appendChild(tr);
        });

        const historySection = document.getElementById('modal-history-section');
        if (req.status !== 'Pendente') {
            historySection.classList.remove('hidden');
            document.getElementById('modal-history-text').innerHTML =
                `<strong>Atendido por:</strong> ${req.nome_atendente || 'Não informado'}<br><strong>Finalizado em:</strong> ${formatarDataBR(req.atendido_em)}`;
        } else {
            historySection.classList.add('hidden');
        }

        const footer = document.getElementById('modal-footer');
        footer.innerHTML = req.status === 'Pendente' ? `
            <button onclick="fecharModal()" class="px-4 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-semibold">Fechar</button>
            <button onclick="dispensarRequisicao()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-bold flex items-center space-x-1 shadow-sm"><i class="fa-solid fa-truck-ramp-box"></i><span>Dispensar Insumos</span></button>` : `
            <button onclick="fecharModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-sm font-semibold">Fechar</button>`;

        const modal = document.getElementById('detail-modal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    } catch (erro) {
        mostrarToast('Erro ao carregar detalhes da requisição.', 'error');
        console.error(erro);
    }
}

function fecharModal() {
    const modal = document.getElementById('detail-modal');
    modal.classList.add('opacity-0');
    modal.querySelector('.transform').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        idRequisicaoAberta = null;
    }, 300);
}

/**
 * Envia a dispensação (com os lotes informados) para salvar no banco.
 */
async function dispensarRequisicao() {
    if (!idRequisicaoAberta) return;

    const camposLote = document.querySelectorAll('.campo-lote');
    const itens = [];
    let valido = true;

    camposLote.forEach(campo => {
        const lote = campo.value.trim();
        if (!lote) {
            campo.classList.add('border-red-500');
            valido = false;
        } else {
            campo.classList.remove('border-red-500');
        }
        itens.push({ id_item: campo.dataset.idItem, lote });
    });

    if (!valido) {
        mostrarToast('Insira o número do lote para todos os itens.', 'error');
        return;
    }

    try {
        const resposta = await fetch('../controllers/requisicao_controller.php?action=dispensar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_requisicao: idRequisicaoAberta, itens }),
        });
        const dados = await resposta.json();

        if (dados.sucesso) {
            fecharModal();
            mostrarToast(`Requisição #REQ-${idRequisicaoAberta} atendida e dispensada com sucesso!`);
            carregarRequisicoes();
        } else {
            mostrarToast(dados.erro || 'Erro ao dispensar.', 'error');
        }
    } catch (erro) {
        mostrarToast('Erro de conexão ao dispensar.', 'error');
    }
}

/**
 * Adiciona uma nova linha de medicamento no formulário de nova requisição.
 */
function adicionarLinhaMedicamento() {
    const container = document.getElementById('medications-rows');
    const idx = container.children.length;
    const div = document.createElement('div');
    div.className = 'flex items-center space-x-2 bg-slate-50 p-3 rounded-xl border border-slate-200 focus-within:border-teal-500';
    div.id = `med-row-${idx}`;

    div.innerHTML = `
        <div class="flex-grow">
            <input type="text" placeholder="Nome do medicamento..." required class="w-full bg-transparent text-sm focus:outline-none placeholder-slate-400 font-medium" name="med-nome[]">
        </div>
        <div class="w-20 border-l border-slate-200 pl-3">
            <input type="number" min="1" value="1" required class="w-full bg-transparent text-sm focus:outline-none font-bold text-center text-slate-700" name="med-qtde[]">
        </div>
        <button type="button" onclick="removerLinhaMedicamento('${div.id}')" class="p-1.5 text-slate-400 hover:text-red-500 rounded-lg transition"><i class="fa-solid fa-trash-can"></i></button>
    `;
    container.appendChild(div);
}

function removerLinhaMedicamento(idLinha) {
    const container = document.getElementById('medications-rows');
    if (container.children.length > 1) {
        document.getElementById(idLinha).remove();
    } else {
        mostrarToast('Mínimo de 1 medicamento requerido.', 'error');
    }
}

/**
 * Envia a nova requisição (paciente, setor, prioridade, itens) para o banco.
 */
async function criarRequisicao(evento) {
    evento.preventDefault();

    const idFarmaciaDestino = document.getElementById('form-farmacia-destino').value;
    const nomePaciente = document.getElementById('form-paciente').value.trim();
    const setorOrigem = document.getElementById('form-setor').value;
    const prioridade = document.querySelector('input[name="form-prioridade"]:checked').value;

    const nomes = document.getElementsByName('med-nome[]');
    const quantidades = document.getElementsByName('med-qtde[]');
    const itens = [];

    for (let i = 0; i < nomes.length; i++) {
        if (nomes[i].value.trim()) {
            itens.push({ nome_medicamento: nomes[i].value.trim(), quantidade: parseInt(quantidades[i].value) || 1 });
        }
    }

    if (!idFarmaciaDestino || itens.length === 0) {
        mostrarToast('Selecione a farmácia destino e ao menos um medicamento.', 'error');
        return;
    }

    try {
        const resposta = await fetch('../controllers/requisicao_controller.php?action=criar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_farmacia_destino: idFarmaciaDestino,
                nome_paciente: nomePaciente,
                setor_origem: setorOrigem,
                prioridade,
                itens,
            }),
        });
        const dados = await resposta.json();

        if (dados.sucesso) {
            mostrarToast(`Requisição #REQ-${dados.id_requisicao} criada com sucesso!`);
            document.getElementById('new-req-form').reset();
            document.getElementById('medications-rows').innerHTML = '';
            adicionarLinhaMedicamento();
            trocarAba('atender');
        } else {
            mostrarToast(dados.erro || 'Erro ao criar requisição.', 'error');
        }
    } catch (erro) {
        mostrarToast('Erro de conexão ao criar requisição.', 'error');
    }
}

/**
 * Troca de aba. Detecta dinamicamente quais views/botões existem na página
 * (o dashboard do farmacêutico tem uma aba extra "equipe" que o auxiliar não tem).
 */
function trocarAba(nomeAba) {
    const nomesPossiveis = ['atender', 'requisitar', 'minhas', 'perfil', 'equipe'];

    nomesPossiveis.forEach(chave => {
        const view = document.getElementById(`view-${chave}`);
        const botao = document.getElementById(`nav-${chave}`);
        if (!view || !botao) return; // aba não existe neste dashboard, ignora

        if (chave === nomeAba) {
            view.classList.remove('hidden');
            botao.className = 'px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 bg-white/10 text-white hover:bg-white/20 border border-white/10 flex items-center space-x-2 shadow-inner';
        } else {
            view.classList.add('hidden');
            botao.className = 'px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-teal-100 hover:text-white hover:bg-white/10 flex items-center space-x-2';
        }
    });

    if (nomeAba === 'atender') {
        carregarRequisicoes();
    } else if (nomeAba === 'minhas' && typeof carregarMinhasAtividades === 'function') {
        carregarMinhasAtividades();
    } else if (nomeAba === 'perfil' && typeof carregarPerfil === 'function') {
        carregarPerfil();
    } else if (nomeAba === 'equipe' && typeof carregarEquipe === 'function') {
        carregarEquipe();
    }
}

function mostrarToast(mensagem, tipo = 'success') {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-message');
    const toastIcon = document.getElementById('toast-icon');
    toastMsg.innerText = mensagem;
    toastIcon.innerHTML = tipo === 'success'
        ? '<i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>'
        : '<i class="fa-solid fa-circle-exclamation text-red-400 text-lg"></i>';
    toast.classList.remove('translate-y-20', 'opacity-0');
    setTimeout(() => toast.classList.add('translate-y-20', 'opacity-0'), 3000);
}
