<?php
/**
 * Impressão da OS (fork Conser): quadro "Dados para o motorista" abaixo das
 * assinaturas, só com o que ele precisa para chegar ao cliente e fazer o
 * serviço. Contorno tracejado para poder recortar e levar no caminhão; o QR
 * Code abre o endereço no mapa do celular.
 *
 * @var array{os: string, quando: string, cliente: string, contato: string, endereco: string, referencia: string, responsavel: string, mapa: string} $motorista
 * @var string|null $motoristaQr  data URI do QR Code do mapa
 * @var object      $result       a OS (descrição e observações)
 */
$linhas = array_filter([
    'Data' => $motorista['quando'],
    'Cliente' => $motorista['cliente'],
    'Contato' => $motorista['contato'],
    'Endereço' => $motorista['endereco'],
    'Complemento' => $motorista['referencia'],
    'Responsável' => $motorista['responsavel'],
], static fn ($valor) => $valor !== '');
?>
<section class="mt-4 break-inside-avoid border-2 border-dashed border-ink p-3 text-[15px] leading-snug" aria-label="Dados para o motorista">
    <div class="mb-2 flex items-center justify-between border-b border-hairline-cool pb-1.5">
        <span class="text-base font-bold">DADOS PARA O MOTORISTA</span>
        <span class="text-base font-bold">OS #<?= e($motorista['os']) ?></span>
    </div>
    <div class="flex items-start justify-between gap-4">
        <dl class="grid flex-1 grid-cols-[7.5rem_1fr] gap-x-2 gap-y-1">
            <?php foreach ($linhas as $rotulo => $valor) { ?>
                <dt class="font-bold"><?= e($rotulo) ?>:</dt>
                <dd class="<?= e($rotulo === 'Endereço' || $rotulo === 'Cliente' ? 'font-bold' : '') ?>"><?= e($valor) ?></dd>
            <?php } ?>
            <?php if ($result->descricaoProduto) { ?>
                <dt class="font-bold">Serviço:</dt>
                <dd><?= printSafeHtml($result->descricaoProduto) ?></dd>
            <?php } ?>
            <?php if ($result->observacoes) { ?>
                <dt class="font-bold">Observações:</dt>
                <dd><?= printSafeHtml($result->observacoes) ?></dd>
            <?php } ?>
        </dl>
        <?php if ($motoristaQr) { ?>
            <div class="flex w-[120px] shrink-0 flex-col items-center text-center text-xs">
                <img class="w-[110px]" src="<?= e($motoristaQr) ?>" alt="QR Code com o endereço no mapa">
                <span>Aponte a câmera para abrir no mapa</span>
            </div>
        <?php } ?>
    </div>
</section>
