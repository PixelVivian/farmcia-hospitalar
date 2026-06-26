<?php
/**
 * views/dashboard_auxiliar.php
 * Painel do Auxiliar de Farmácia: mesma fila de requisições e criação
 * de pedidos do farmacêutico, mas sem acesso a cadastro de lotes/estoque.
 */
define('PERFIL_EXIGIDO', 'auxiliar');
require_once __DIR__ . '/../includes/auth_check.php';

$nomeUsuario = htmlspecialchars($_SESSION['nome']);
$siglaFarmacia = htmlspecialchars($_SESSION['sigla_farmacia']);

// Lista de farmácias para o seletor de "farmácia destino" no formulário de nova requisição
require_once __DIR__ . '/../config/db.php';
$farmacias = $pdo->query("SELECT id_farmacia, sigla, nome_completo FROM farmacias WHERE ativo = 1 ORDER BY sigla")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmácia Hospitalar - Painel do Auxiliar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">

    <!-- TOAST DE NOTIFICAÇÃO -->
    <div id="toast" class="fixed bottom-5 right-5 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center space-x-3 border border-slate-700">
            <span id="toast-icon" class="text-teal-400"><i class="fa-solid fa-circle-info"></i></span>
            <p id="toast-message" class="text-sm font-medium"></p>
        </div>
    </div>

    <!-- CABEÇALHO -->
    <header class="bg-gradient-to-r from-teal-700 to-emerald-800 text-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <div class="bg-white/10 p-2 rounded-lg backdrop-blur-sm border border-white/20">
                        <i class="fa-solid fa-heart-pulse text-2xl text-teal-100"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight">Farmácia Hospitalar</h1>
                        <p class="text-xs text-teal-100">Unidade: <?= $siglaFarmacia ?> &middot; Auxiliar de Farmácia</p>
                    </div>
                </div>

                <nav class="flex space-x-2 items-center">
                    <button id="nav-atender" onclick="trocarAba('atender')" class="px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 bg-white/10 text-white hover:bg-white/20 border border-white/10 flex items-center space-x-2 shadow-inner">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <span class="hidden md:inline">Fila Geral</span>
                    </button>
                    <button id="nav-requisitar" onclick="trocarAba('requisitar')" class="px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-teal-100 hover:text-white hover:bg-white/10 flex items-center space-x-2">
                        <i class="fa-solid fa-plus-circle"></i>
                        <span class="hidden md:inline">Criar Pedido</span>
                    </button>
                    <button id="nav-minhas" onclick="trocarAba('minhas')" class="px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-teal-100 hover:text-white hover:bg-white/10 flex items-center space-x-2">
                        <i class="fa-solid fa-receipt"></i>
                        <span class="hidden md:inline">Minhas Atividades</span>
                    </button>
                    <button id="nav-perfil" onclick="trocarAba('perfil')" class="px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 text-teal-100 hover:text-white hover:bg-white/10 flex items-center space-x-2">
                        <i class="fa-solid fa-user-circle"></i>
                        <span class="hidden md:inline">Perfil</span>
                    </button>

                    <div class="h-6 w-px bg-white/25 mx-2"></div>

                    <span class="text-sm text-teal-100 hidden sm:inline"><?= $nomeUsuario ?></span>

                    <a href="../controllers/logout_controller.php" class="p-2 text-teal-100 hover:text-red-200 hover:bg-white/5 rounded-lg text-sm transition flex items-center space-x-1" title="Sair do Sistema">
                        <i class="fa-solid fa-power-off text-lg"></i>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- CARDS DE ESTATÍSTICA -->
    <section class="bg-white border-b border-slate-200 py-4 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-teal-50 p-4 rounded-xl border border-teal-100 flex items-center space-x-4">
                    <div class="p-3 bg-teal-500 rounded-lg text-white"><i class="fa-solid fa-hourglass-half text-lg"></i></div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase font-semibold">Pendentes</p>
                        <h3 id="stat-pending" class="text-2xl font-bold text-teal-800">0</h3>
                    </div>
                </div>
                <div class="bg-amber-50 p-4 rounded-xl border border-amber-100 flex items-center space-x-4">
                    <div class="p-3 bg-amber-500 rounded-lg text-white"><i class="fa-solid fa-triangle-exclamation text-lg"></i></div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase font-semibold">Críticas / Urgentes</p>
                        <h3 id="stat-urgent" class="text-2xl font-bold text-amber-800">0</h3>
                    </div>
                </div>
                <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-100 flex items-center space-x-4">
                    <div class="p-3 bg-emerald-500 rounded-lg text-white"><i class="fa-solid fa-circle-check text-lg"></i></div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase font-semibold">Atendidas</p>
                        <h3 id="stat-served" class="text-2xl font-bold text-emerald-800">0</h3>
                    </div>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex items-center space-x-4">
                    <div class="p-3 bg-slate-500 rounded-lg text-white"><i class="fa-solid fa-boxes-stacked text-lg"></i></div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase font-semibold">Total Geral</p>
                        <h3 id="stat-total" class="text-2xl font-bold text-slate-800">0</h3>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- ABA: FILA DE ATENDIMENTO -->
        <div id="view-atender" class="space-y-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="relative w-full md:w-96">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="search-input" placeholder="Buscar por paciente ou medicação..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 text-sm">
                </div>

                <div class="flex flex-wrap gap-2 w-full md:w-auto justify-end">
                    <select id="filter-status" class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:outline-none">
                        <option value="Pendente">Apenas Pendentes</option>
                        <option value="Atendido">Apenas Atendidos</option>
                        <option value="Cancelado">Apenas Cancelados</option>
                        <option value="Todos">Todos os Status</option>
                    </select>

                    <button onclick="trocarAba('requisitar')" class="bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition duration-200 flex items-center space-x-2 shadow-sm">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nova Requisição</span>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">
                    <h2 class="font-bold text-slate-700 flex items-center space-x-2">
                        <span class="inline-block w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
                        <span>Requisições da Unidade <?= $siglaFarmacia ?></span>
                    </h2>
                    <span id="showing-count" class="text-xs font-semibold text-slate-500 bg-slate-200/60 px-2.5 py-1 rounded-full">Carregando...</span>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase bg-slate-50">
                                <th class="px-6 py-3.5">ID / Prioridade</th>
                                <th class="px-6 py-3.5">Paciente</th>
                                <th class="px-6 py-3.5">Setor de Origem</th>
                                <th class="px-6 py-3.5">Data &amp; Hora</th>
                                <th class="px-6 py-3.5">Itens</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="requisitions-list" class="divide-y divide-slate-100 text-sm"></tbody>
                    </table>
                </div>

                <div id="empty-state" class="hidden py-16 text-center">
                    <div class="text-slate-300 text-5xl mb-4"><i class="fa-solid fa-box-open"></i></div>
                    <p class="text-slate-500 font-semibold text-lg">Nenhuma requisição encontrada</p>
                    <p class="text-slate-400 text-sm max-w-sm mx-auto mt-1">Ajuste os filtros ou busque outro termo.</p>
                </div>
            </div>
        </div>

        <!-- ABA: NOVA REQUISIÇÃO -->
        <div id="view-requisitar" class="hidden max-w-3xl mx-auto">
            <div class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden">
                <div class="bg-gradient-to-r from-teal-700 to-emerald-800 text-white p-6">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-notes-medical text-3xl"></i>
                        <div>
                            <h2 class="text-xl font-bold">Criar Nova Requisição de Medicamentos</h2>
                            <p class="text-teal-100/90 text-xs mt-1">Preencha os dados e selecione a farmácia que vai atender o pedido.</p>
                        </div>
                    </div>
                </div>

                <form id="new-req-form" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 tracking-wider mb-2">Nome do Paciente (opcional)</label>
                            <div class="relative">
                                <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="form-paciente" placeholder="Ex: Maria de Souza Santos" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 focus:ring-2 focus:ring-teal-500 focus:outline-none text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 tracking-wider mb-2">Setor Solicitante</label>
                            <div class="relative">
                                <i class="fa-solid fa-hospital-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <select id="form-setor" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 focus:ring-2 focus:ring-teal-500 focus:outline-none text-sm bg-white">
                                    <option value="">Selecione o Setor</option>
                                    <option value="UTI Adulto">UTI Adulto</option>
                                    <option value="UTI Pediátrica">UTI Pediátrica</option>
                                    <option value="Emergência">Emergência</option>
                                    <option value="Bloco Cirúrgico">Bloco Cirúrgico</option>
                                    <option value="Clínica Médica">Clínica Médica</option>
                                    <option value="Oncologia">Oncologia</option>
                                    <option value="Maternidade">Maternidade</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 tracking-wider mb-2">Farmácia Destino (quem vai atender)</label>
                            <div class="relative">
                                <i class="fa-solid fa-hospital absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <select id="form-farmacia-destino" required class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 focus:ring-2 focus:ring-teal-500 focus:outline-none text-sm bg-white">
                                    <option value="" disabled selected>Selecione a unidade</option>
                                    <?php foreach ($farmacias as $f): ?>
                                        <option value="<?= $f['id_farmacia'] ?>"><?= htmlspecialchars($f['sigla']) ?> - <?= htmlspecialchars($f['nome_completo']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 tracking-wider mb-2">Nível de Prioridade</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="border border-slate-200 rounded-lg p-2.5 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition [&:has(input:checked)]:border-emerald-500 [&:has(input:checked)]:bg-emerald-50/50">
                                    <input type="radio" name="form-prioridade" value="Rotina" checked class="sr-only">
                                    <i class="fa-solid fa-circle-info text-emerald-500 mb-1"></i>
                                    <span class="text-[10px] font-semibold">Rotina</span>
                                </label>
                                <label class="border border-slate-200 rounded-lg p-2.5 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition [&:has(input:checked)]:border-amber-500 [&:has(input:checked)]:bg-amber-50/50">
                                    <input type="radio" name="form-prioridade" value="Alta" class="sr-only">
                                    <i class="fa-solid fa-triangle-exclamation text-amber-500 mb-1"></i>
                                    <span class="text-[10px] font-semibold">Alta</span>
                                </label>
                                <label class="border border-slate-200 rounded-lg p-2.5 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition [&:has(input:checked)]:border-red-500 [&:has(input:checked)]:bg-red-50/50">
                                    <input type="radio" name="form-prioridade" value="Urgente" class="sr-only">
                                    <i class="fa-solid fa-bolt text-red-500 mb-1"></i>
                                    <span class="text-[10px] font-semibold">Urgente</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Itens Solicitados</h3>
                            <button type="button" onclick="adicionarLinhaMedicamento()" class="text-xs bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 px-3 py-1.5 rounded-lg font-semibold transition flex items-center space-x-1">
                                <i class="fa-solid fa-plus-circle"></i>
                                <span>Adicionar Item</span>
                            </button>
                        </div>
                        <div id="medications-rows" class="space-y-3"></div>
                    </div>

                    <div class="border-t border-slate-150 pt-6 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="button" onclick="trocarAba('atender')" class="px-5 py-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 font-semibold text-sm text-slate-600 transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-xmark"></i>
                            <span>Cancelar</span>
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm transition shadow-md flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Enviar Requisição</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ABA: MINHAS ATIVIDADES (requisições que EU já atendi/cancelei) -->
        <div id="view-minhas" class="hidden space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
                    <h2 class="font-bold text-slate-700 flex items-center space-x-2">
                        <i class="fa-solid fa-clock-rotate-left text-teal-600"></i>
                        <span>Requisições que eu atendi ou cancelei</span>
                    </h2>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase bg-slate-50">
                                <th class="px-6 py-3.5">ID / Status</th>
                                <th class="px-6 py-3.5">Paciente</th>
                                <th class="px-6 py-3.5">Setor de Origem</th>
                                <th class="px-6 py-3.5">Itens</th>
                                <th class="px-6 py-3.5">Finalizado em</th>
                            </tr>
                        </thead>
                        <tbody id="my-activities-list" class="divide-y divide-slate-100 text-sm"></tbody>
                    </table>
                </div>

                <div id="my-activities-empty" class="hidden py-16 text-center">
                    <div class="text-slate-300 text-5xl mb-4"><i class="fa-solid fa-file-invoice"></i></div>
                    <p class="text-slate-500 font-semibold text-lg">Você ainda não atendeu nenhuma requisição</p>
                </div>
            </div>
        </div>

        <!-- ABA: PERFIL -->
        <div id="view-perfil" class="hidden max-w-2xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

                <!-- Cabeçalho do Perfil: ícone + nome + cargo + unidade -->
                <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-6 border-b border-slate-100 p-6">
                    <div class="w-24 h-24 rounded-2xl bg-slate-100 border-4 border-white shadow-md flex items-center justify-center text-slate-300 text-5xl flex-shrink-0">
                        <i class="fa-solid fa-user-circle"></i>
                    </div>
                    <div class="text-center sm:text-left space-y-1">
                        <h2 id="perfil-nome" class="text-2xl font-black text-slate-800">-</h2>
                        <p id="perfil-tipo" class="text-sm text-teal-700 font-bold">-</p>
                        <p class="text-xs text-slate-500 flex items-center justify-center sm:justify-start space-x-1">
                            <i class="fa-solid fa-building-user"></i>
                            <span id="perfil-farmacia">-</span>
                        </p>
                    </div>
                </div>

                <!-- Dados adicionais do perfil -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">E-mail</span>
                        <span id="perfil-email" class="text-sm font-semibold text-slate-700 block mt-1">-</span>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Login</span>
                        <span id="perfil-login" class="text-sm font-semibold text-slate-700 block mt-1">-</span>
                    </div>
                </div>

                <!-- Troca de farmácia -->
                <div class="border-t border-slate-100 p-6 pt-5">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">Trocar de Farmácia</h3>
                    <form id="form-trocar-farmacia" class="flex flex-col sm:flex-row gap-3">
                        <select id="perfil-select-farmacia" class="flex-grow px-3 py-2.5 rounded-lg border border-slate-200 focus:ring-2 focus:ring-teal-500 focus:outline-none text-sm bg-white"></select>
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm transition shadow-sm flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-right-left"></i>
                            <span>Trocar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL DETALHES & ATENDIMENTO -->
    <div id="detail-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl border border-slate-200 overflow-hidden transform scale-95 transition-transform duration-300 flex flex-col max-h-[90vh]">
            <div class="p-5 border-b border-slate-100 flex justify-between items-start bg-slate-50">
                <div>
                    <div class="flex items-center space-x-2">
                        <span id="modal-priority-badge" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">PRIORIDADE</span>
                        <span id="modal-id" class="text-xs font-semibold text-slate-400">#REQ-000</span>
                    </div>
                    <h3 id="modal-title" class="text-lg font-bold text-slate-800 mt-1">Atendimento de Paciente</h3>
                    <p id="modal-subtitle" class="text-xs text-slate-500 mt-0.5">Setor de Origem</p>
                </div>
                <button onclick="fecharModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto custom-scrollbar flex-grow space-y-6">
                <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-150">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Data de Solicitação</p>
                        <p id="modal-date" class="text-sm font-semibold text-slate-700"></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Status Atual</p>
                        <span id="modal-status-badge" class="px-2.5 py-1 rounded-full text-xs font-semibold inline-block mt-0.5">Pendente</span>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider mb-3">Insumos e Medicamentos Solicitados</h4>
                    <div class="border border-slate-150 rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-150 text-xs font-bold text-slate-500 uppercase">
                                    <th class="px-4 py-2.5">Medicamento</th>
                                    <th class="px-4 py-2.5 text-center">Quantidade</th>
                                    <th class="px-4 py-2.5">Lote / Validade</th>
                                </tr>
                            </thead>
                            <tbody id="modal-items-tbody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <div id="modal-history-section" class="hidden border-t border-slate-150 pt-4 space-y-2">
                    <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider">Metadados de Finalização</h4>
                    <p id="modal-history-text" class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200"></p>
                </div>
            </div>

            <div id="modal-footer" class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end space-x-2"></div>
        </div>
    </div>

    <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p class="font-medium">Sistema de Farmácia Hospitalar &middot; Gerenciamento de Requisições e Estoque.</p>
        </div>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/usuario.js"></script>
</body>
</html>
