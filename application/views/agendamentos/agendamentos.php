<?php
/**
 * Listagem de agendamentos (fork Conser), no padrão das listagens da v5
 * (views/clientes/clientes.php): filtros em GET, data-table que vira cartões
 * abaixo de 640px, empty-state para "nada agendado" e "nada encontrado",
 * paginação que mantém os filtros, "Novo agendamento" na topbar e exclusão
 * com um modal-confirm só, preenchido pelo gatilho da linha (data-valor-*).
 *
 * Sem filtro de período a tela mostra os próximos (de hoje em diante).
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var string                $hoje
 * @var int                   $total
 * @var bool                  $controle_editos
 * @var array                 $paginacao
 * @var array{adicionar: bool, editar: bool, excluir: bool, ver_os: bool} $pode
 */
$periodo = $filtros['periodo'] ?? 'proximos';
// O período padrão não conta como filtro do usuário (empty-state e "Limpar").
$temFiltro = array_diff_key($filtros, ['periodo' => true]) !== [] || $periodo !== 'proximos';

$colunas = [
    ['label' => 'Data', 'nowrap' => true, 'render' => function ($a) use ($hoje) {
        $quando = dataBr($a->dataFinal) . (agendamentoHoraCurta($a->hora) !== '' ? ' · ' . agendamentoHoraCurta($a->hora) : '');
        $partes = [$quando];
        if (agendamentoAtrasado($a, $hoje)) {
            $partes[] = component('pill-status', ['label' => 'Atrasado', 'variant' => 'warning']);
        } elseif ($a->dataFinal === $hoje) {
            $partes[] = component('pill-status', ['label' => 'Hoje', 'variant' => 'info']);
        }

        return $partes;
    }],
    ['label' => 'Cliente', 'class' => 'min-w-40', 'render' => fn ($a) => $a->nomeCliente !== null
        ? component('link', ['label' => $a->nomeCliente, 'href' => site_url('clientes/visualizar/' . $a->clientes_id), 'class' => 'font-medium'])
        : 'Cliente removido'],
    ['label' => 'Telefone', 'nowrap' => true, 'hide_until' => 'md', 'render' => fn ($a) => (string) ($a->celular ?: $a->telefone)],
    ['label' => 'Serviço', 'hide_until' => 'lg', 'class' => 'max-w-64', 'render' => fn ($a) => osTextoCurto($a->descricaoProduto, 60)],
    ['key' => 'responsavel', 'label' => 'Técnico', 'nowrap' => true, 'hide_until' => 'xl'],
    ['label' => 'OS', 'nowrap' => true, 'render' => fn ($a) => [
        $pode['ver_os']
            ? component('link', ['label' => '#' . $a->os_id, 'href' => site_url('os/visualizar/' . $a->os_id), 'class' => 'tabular-nums'])
            : '#' . $a->os_id,
        component('pill-status', osStatusPill($a->status)),
    ]],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($a) use ($pode, $controle_editos) {
        $nome = 'agendamento de ' . ($a->nomeCliente ?? 'cliente removido') . ' em ' . dataBr($a->dataFinal);
        $acoes = [];
        if ($pode['ver_os']) {
            $acoes[] = component('button', ['label' => 'Ver OS ' . $a->os_id, 'icon' => 'eye', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('os/visualizar/' . $a->os_id)]);
        }
        if (osEditavel($a, $pode['editar'], $controle_editos)) {
            $acoes[] = component('button', ['label' => 'Editar ' . $nome, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('agendamentos/editar/' . $a->idAgendamento)]);
        }
        if ($pode['excluir']) {
            $acoes[] = component('button', ['label' => 'Excluir ' . $nome, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => [
                'data-modal-abrir' => 'excluir-agendamento',
                'data-valor-id' => (string) $a->idAgendamento,
                'data-valor-descricao' => ($a->nomeCliente ?? 'cliente removido') . ' em ' . dataBr($a->dataFinal),
            ]]);
        }

        return $acoes;
    }],
];

$vazio = $temFiltro
    ? component('empty-state', [
        'title' => 'Nenhum agendamento encontrado',
        'message' => $periodo === 'atrasados' && array_diff_key($filtros, ['periodo' => true]) === []
            ? 'Nenhuma visita passou da data sem ser finalizada.'
            : 'Nada corresponde aos filtros. Confira a busca ou limpe os filtros.',
        'icon' => 'search-x',
        'action' => component('button', ['label' => 'Limpar filtros', 'variant' => 'outline', 'href' => site_url('agendamentos')]),
        'class' => 'border-0',
    ])
    : component('empty-state', [
        'title' => 'Nenhum agendamento pela frente',
        'message' => 'As próximas visitas agendadas aparecem aqui. Para ver as antigas, escolha o período "Todos".',
        'icon' => 'clock',
        'action' => $pode['adicionar'] ? component('button', ['label' => 'Agendar visita', 'icon' => 'plus', 'variant' => 'outline', 'href' => site_url('agendamentos/adicionar')]) : null,
        'class' => 'border-0',
    ]);

$resumo = ($total === 1 ? '1 agendamento' : number_format($total, 0, ',', '.') . ' agendamentos')
    . ' · ' . mb_strtolower(AGENDAMENTO_PERIODOS[$periodo])
    . (array_diff_key($filtros, ['periodo' => true]) !== [] ? ' com os filtros' : '');
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Agendamentos</h1>
        <p class="text-caption text-muted" aria-live="polite"><?= e($resumo) ?></p>
    </header>

    <form method="get" action="<?= e(site_url('agendamentos')) ?>" role="search" aria-label="Filtrar agendamentos" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_10rem_10rem_10rem_auto]">
        <?= component('input', [
            'name' => 'pesquisa',
            'label' => 'Buscar',
            'type' => 'search',
            'value' => $filtros['pesquisa'] ?? null,
            'placeholder' => 'Cliente, telefone, serviço ou Nº da OS',
            'attrs' => ['maxlength' => 100],
        ]) ?>
        <?= component('select', [
            'name' => 'periodo',
            'label' => 'Período',
            'options' => AGENDAMENTO_PERIODOS,
            'selected' => $periodo,
        ]) ?>
        <?= component('input', [
            'name' => 'de',
            'label' => 'De',
            'type' => 'date',
            'value' => $filtros['de'] ?? null,
        ]) ?>
        <?= component('input', [
            'name' => 'ate',
            'label' => 'Até',
            'type' => 'date',
            'value' => $filtros['ate'] ?? null,
        ]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('agendamentos')]) ?>
            <?php } ?>
        </div>
    </form>

    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $results,
        'empty' => $vazio,
        'caption' => 'Agendamentos',
    ]) ?>

    <?= component('pagination', $paginacao) ?>
</div>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-agendamento" method="post" action="<?= e(site_url('agendamentos/excluir') . listagemQuery($filtros)) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-agendamento" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-agendamento',
        'title' => 'Excluir agendamento?',
        // A descrição entra pelo modal.js (textContent) a partir do
        // data-valor-descricao do botão da linha.
        'message' => [
            'O agendamento de ',
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="descricao"></strong>'),
            ' será removido. A OS criada por ele sai junto se ainda não tiver nada lançado; senão, ela fica.',
        ],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-agendamento'],
    ]) ?>
<?php } ?>
