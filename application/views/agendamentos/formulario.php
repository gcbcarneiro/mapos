<?php
/**
 * Formulário de agendamento (fork Conser), no padrão dos formulários da v5
 * (views/os/formulario.php).
 *
 * - Cliente e técnico são os mesmos autocompletes da OS (lib/autocomplete.js,
 *   ligados pelo módulo agendamentos/formulario): o texto fica no campo
 *   visível e o id no campo oculto ao lado.
 * - Os atalhos "Em 3 meses", "Em 6 meses" e "Em 1 ano" preenchem a data (as
 *   datas vêm calculadas do servidor, em data-data).
 * - Salvar cria a OS do cliente com a data do agendamento; ao editar, a OS é
 *   atualizada junto (o status continua sendo mudado na tela da OS).
 *
 * @var object|null           $agendamento  null ao cadastrar
 * @var array<string, string> $valores      agendamentoValoresDoFormulario()
 * @var array<string, string> $erros        Por campo; _geral para falha ao salvar
 * @var string                $hoje         Y-m-d
 * @var array<string, string> $atalhos      agendamentoAtalhosDeData()
 * @var array<string, bool>   $pode
 */
$editando = $agendamento !== null;
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$grade = 'mt-4 grid gap-4 sm:grid-cols-2';

$visivel = static fn (string $nome, array $props) => $props + [
    'name' => $nome,
    'value' => $valores[$nome] !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
    'autocomplete' => 'off',
];
$wrapper = static fn (string $url, string $oculto, array $extras = []) => ['class' => 'relative', 'data-autocomplete' => $url, 'data-autocomplete-alvo' => componenteId($oculto)] + $extras;
$oculto = static fn (string $nome) => ['type' => 'hidden', 'id' => componenteId($nome), 'name' => $nome, 'value' => $valores[$nome]];

$statusOpcoes = array_combine(AGENDAMENTO_STATUS_INICIAIS, AGENDAMENTO_STATUS_INICIAIS);
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8" <?= js_module('agendamentos/formulario') ?>>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-heading-xl text-text"><?= e($editando ? 'Editar agendamento' : 'Novo agendamento') ?></h1>
                <?php if ($editando) { ?>
                    <?= component('pill-status', osStatusPill($agendamento->status)) ?>
                <?php } ?>
            </div>
            <p class="text-caption text-muted"><?= e($editando
                ? 'OS #' . $agendamento->os_id . ' · ' . ($agendamento->nomeCliente ?? 'cliente removido')
                : 'Ao salvar, uma OS é criada para o cliente com a data do agendamento.') ?></p>
        </div>
        <?php if ($pode['ver_os']) { ?>
            <?= component('button', [
                'label' => 'Ver OS #' . $agendamento->os_id,
                'icon' => 'eye',
                'variant' => 'outline',
                'href' => site_url('os/visualizar/' . (int) $agendamento->os_id),
            ]) ?>
        <?php } ?>
    </header>

    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-cliente">
            <h2 id="secao-cliente" class="text-heading-sm text-text">Cliente e responsável</h2>
            <div class="<?= e($grade) ?>">
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteCliente'), 'clientes_id', $pode['cadastrar_cliente'] ? [
                    'data-autocomplete-novo' => site_url('clientes/adicionar'),
                    'data-autocomplete-novo-modal' => 'cliente-rapido',
                    'data-autocomplete-novo-rotulo' => 'Cadastrar novo cliente',
                ] : [])) ?>>
                    <?= component('input', $visivel('cliente', [
                        'label' => 'Cliente',
                        'required' => true,
                        'placeholder' => 'Nome, documento ou telefone',
                        'attrs' => [
                            'autofocus' => ! $editando,
                            'data-msg-vazio' => 'Escolha o cliente do agendamento.',
                            'data-msg-invalido' => 'Escolha um cliente da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('clientes_id')) ?>>
                </div>
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteUsuario'), 'usuarios_id')) ?>>
                    <?= component('input', $visivel('tecnico', [
                        'label' => 'Técnico responsável',
                        'required' => true,
                        'placeholder' => 'Nome do técnico',
                        'attrs' => [
                            'data-msg-vazio' => 'Escolha o técnico responsável.',
                            'data-msg-invalido' => 'Escolha um técnico da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('usuarios_id')) ?>>
                </div>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-quando">
            <h2 id="secao-quando" class="text-heading-sm text-text">Quando</h2>
            <div class="<?= e($grade) ?>">
                <div class="flex flex-col gap-2">
                    <?= component('input', [
                        'name' => 'data',
                        'label' => 'Data do agendamento',
                        'type' => 'date',
                        'required' => true,
                        'value' => $valores['data'] !== '' ? $valores['data'] : null,
                        'error' => $errosDeCampo['data'] ?? null,
                        'help' => 'Vira a data de entrada e de entrega da OS.',
                        'attrs' => array_filter([
                            'min' => $editando ? null : $hoje,
                            'data-msg-vazio' => 'Informe a data do agendamento.',
                            'data-msg-invalido' => $editando ? null : 'A data não pode ser anterior a hoje.',
                            'data-agendamento-data' => true,
                        ], static fn ($valor) => $valor !== null),
                    ]) ?>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Atalhos de data">
                        <?php foreach ($atalhos as $rotulo => $data) { ?>
                            <?= component('button', [
                                'label' => $rotulo,
                                'variant' => 'outline',
                                'size' => 'sm',
                                'attrs' => ['data-agendamento-atalho' => $data, 'title' => dataBr($data)],
                            ]) ?>
                        <?php } ?>
                    </div>
                </div>
                <?= component('input', [
                    'name' => 'hora',
                    'label' => 'Hora',
                    'type' => 'time',
                    'value' => $valores['hora'] !== '' ? $valores['hora'] : null,
                    'error' => $errosDeCampo['hora'] ?? null,
                    'help' => 'Opcional.',
                    'attrs' => ['data-msg-invalido' => 'Informe a hora no formato 00:00.'],
                ]) ?>
                <?php if (! $editando) { ?>
                    <?= component('select', [
                        'name' => 'status',
                        'label' => 'Status inicial da OS',
                        'required' => true,
                        'options' => $statusOpcoes,
                        'selected' => $valores['status'] !== '' ? $valores['status'] : null,
                        'error' => $errosDeCampo['status'] ?? null,
                        'help' => 'Depois o status muda na tela da OS.',
                    ]) ?>
                <?php } ?>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-servico">
            <h2 id="secao-servico" class="text-heading-sm text-text">Serviço</h2>
            <div class="<?= e($grade) ?>">
                <?= component('textarea', [
                    'name' => 'descricao',
                    'label' => 'O que será feito',
                    'required' => true,
                    'rows' => 4,
                    'value' => $valores['descricao'] !== '' ? $valores['descricao'] : null,
                    'error' => $errosDeCampo['descricao'] ?? null,
                    'help' => 'Vai para a descrição da OS. Ex.: manutenção preventiva anual.',
                    'attrs' => ['data-msg-vazio' => 'Descreva o serviço que será feito.'],
                ]) ?>
                <?= component('textarea', [
                    'name' => 'observacoes',
                    'label' => 'Observações',
                    'rows' => 4,
                    'value' => $valores['observacoes'] !== '' ? $valores['observacoes'] : null,
                    'error' => $errosDeCampo['observacoes'] ?? null,
                    'help' => 'Endereço, contato no local, o que levar.',
                ]) ?>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('agendamentos')]) ?>
            <?= component('button', ['label' => $editando ? 'Salvar alterações' : 'Agendar e criar OS', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>

    <?php if ($pode['cadastrar_cliente']) { ?>
        <?php $this->load->view('clientes/partes/cadastro-rapido') ?>
    <?php } ?>
</div>
