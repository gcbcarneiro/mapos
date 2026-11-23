<?php

/**
 * Regras do módulo de agendamentos (fork Conser), em funções puras para
 * serem testadas sem banco nem sessão. Usa o os_helper (osTextoParaGravar,
 * osTextoParaEdicao) e o status_helper (OS_STATUS_VARIANTES).
 */
if (! defined('AGENDAMENTO_STATUS_INICIAIS')) {
    // Status que a OS criada pelo agendamento pode ter: ainda não executada.
    define('AGENDAMENTO_STATUS_INICIAIS', ['Aberto', 'Orçamento', 'Aprovado']);
}

if (! defined('AGENDAMENTO_STATUS_ENCERRADOS')) {
    // OS nestes status não contam como agendamento pendente (nem atrasado).
    define('AGENDAMENTO_STATUS_ENCERRADOS', ['Finalizado', 'Faturado', 'Cancelado']);
}

if (! defined('AGENDAMENTO_PERIODOS')) {
    // Filtro de período da listagem; sem filtro, a tela mostra os próximos.
    define('AGENDAMENTO_PERIODOS', [
        'proximos' => 'Próximos',
        'atrasados' => 'Atrasados',
        'todos' => 'Todos',
    ]);
}

if (! defined('AGENDAMENTO_CAMPOS')) {
    define('AGENDAMENTO_CAMPOS', ['cliente', 'clientes_id', 'tecnico', 'usuarios_id', 'data', 'hora', 'status', 'descricao', 'observacoes']);
}

if (! function_exists('agendamentoHoraValida')) {
    /** "HH:MM" (ou "HH:MM:SS") válida para "HH:MM:00", vazio para null, inválida para false. */
    function agendamentoHoraValida(string $hora): string|false|null
    {
        $hora = trim($hora);
        if ($hora === '') {
            return null;
        }

        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora, $m)) {
            return false;
        }

        return $m[1] . ':' . $m[2] . ':00';
    }
}

if (! function_exists('agendamentoDadosDoFormulario')) {
    /**
     * Converte e confere o POST do formulário.
     *
     * Devolve os dados da OS (só os campos que o agendamento controla), os do
     * agendamento e os erros por campo. A data vira a data inicial e a final da
     * OS, que é o que a agenda do painel e a listagem de OS usam. Ao cadastrar,
     * a data não pode ser anterior a hoje e o status inicial precisa ser um dos
     * AGENDAMENTO_STATUS_INICIAIS; ao editar, o status fica com a tela da OS.
     *
     * @param  array<string, mixed>  $post
     * @param  string                $hoje   Y-m-d
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, string>}
     */
    function agendamentoDadosDoFormulario(array $post, string $hoje, bool $novo): array
    {
        $erros = [];
        $texto = static fn (string $campo): string => is_scalar($post[$campo] ?? null) ? trim((string) $post[$campo]) : '';
        $id = static fn (string $campo): ?int => ctype_digit($texto($campo)) && (int) $texto($campo) > 0 ? (int) $texto($campo) : null;

        $clienteId = $id('clientes_id');
        if ($clienteId === null) {
            $erros['cliente'] = 'Escolha um cliente da lista.';
        }

        $tecnicoId = $id('usuarios_id');
        if ($tecnicoId === null) {
            $erros['tecnico'] = 'Escolha um técnico da lista.';
        }

        $data = dataIsoParaYmd($texto('data'));
        if ($data === null) {
            $erros['data'] = 'Informe uma data válida.';
        } elseif ($novo && $data < $hoje) {
            $erros['data'] = 'A data do agendamento não pode ser anterior a hoje.';
        }

        $hora = agendamentoHoraValida($texto('hora'));
        if ($hora === false) {
            $erros['hora'] = 'Informe a hora no formato 00:00.';
            $hora = null;
        }

        $os = [
            'clientes_id' => $clienteId,
            'usuarios_id' => $tecnicoId,
            'dataInicial' => $data,
            'dataFinal' => $data,
            'descricaoProduto' => osTextoParaGravar($texto('descricao')),
            'observacoes' => osTextoParaGravar($texto('observacoes')),
        ];

        if ($novo) {
            $status = $texto('status');
            if (! in_array($status, AGENDAMENTO_STATUS_INICIAIS, true)) {
                $erros['status'] = 'Escolha um status da lista.';
            }
            $os['status'] = $status;
        }

        if ($os['descricaoProduto'] === '') {
            $erros['descricao'] = 'Descreva o serviço que será feito.';
        }

        return [$os, ['hora' => $hora], $erros];
    }
}

if (! function_exists('agendamentoValoresDoFormulario')) {
    /**
     * Valores dos campos: o POST quando a tela volta com erro, o agendamento
     * ao editar ou os padrões ao cadastrar.
     *
     * @param  array<string, mixed>|null  $post
     * @param  array<string, string>      $padrao
     * @return array<string, string>
     */
    function agendamentoValoresDoFormulario(?array $post, ?object $agendamento, array $padrao = []): array
    {
        if ($post !== null) {
            $valores = [];
            foreach (AGENDAMENTO_CAMPOS as $campo) {
                $valores[$campo] = is_scalar($post[$campo] ?? null) ? trim((string) $post[$campo]) : '';
            }

            return $valores;
        }

        if ($agendamento !== null) {
            return [
                'cliente' => (string) ($agendamento->nomeCliente ?? ''),
                'clientes_id' => (string) ($agendamento->clientes_id ?? ''),
                'tecnico' => (string) ($agendamento->responsavel ?? ''),
                'usuarios_id' => (string) ($agendamento->usuarios_id ?? ''),
                'data' => (string) ($agendamento->dataFinal ?? ''),
                'hora' => agendamentoHoraCurta($agendamento->hora ?? null),
                'status' => (string) ($agendamento->status ?? ''),
                'descricao' => osTextoParaEdicao($agendamento->descricaoProduto ?? null),
                'observacoes' => osTextoParaEdicao($agendamento->observacoes ?? null),
            ];
        }

        return array_merge(array_fill_keys(AGENDAMENTO_CAMPOS, ''), $padrao);
    }
}

if (! function_exists('agendamentoHoraCurta')) {
    /** "14:30:00" para "14:30"; vazio sem hora. */
    function agendamentoHoraCurta(?string $hora): string
    {
        return $hora !== null && preg_match('/^(\d{2}:\d{2})/', $hora, $m) ? $m[1] : '';
    }
}

if (! function_exists('agendamentoAtalhosDeData')) {
    /**
     * Datas dos atalhos do formulário (daqui a 3 e 6 meses e 1 ano), contadas
     * a partir de hoje. Num dia que não existe no mês de destino (31/08 + 6
     * meses), vale o último dia daquele mês.
     *
     * @return array<string, string> rótulo => Y-m-d
     */
    function agendamentoAtalhosDeData(string $hoje): array
    {
        $atalhos = [];
        foreach (['Em 3 meses' => 3, 'Em 6 meses' => 6, 'Em 1 ano' => 12] as $rotulo => $meses) {
            $base = new DateTimeImmutable($hoje);
            $destino = $base->modify('first day of this month')->modify('+' . $meses . ' months');
            $dia = min((int) $base->format('d'), (int) $destino->format('t'));
            $atalhos[$rotulo] = $destino->setDate((int) $destino->format('Y'), (int) $destino->format('m'), $dia)->format('Y-m-d');
        }

        return $atalhos;
    }
}

if (! function_exists('agendamentoAtrasado')) {
    /** Data já passou e a OS ainda não foi finalizada, faturada nem cancelada. */
    function agendamentoAtrasado(object $linha, string $hoje): bool
    {
        return (string) ($linha->dataFinal ?? '') !== ''
            && (string) $linha->dataFinal < $hoje
            && ! in_array((string) ($linha->status ?? ''), AGENDAMENTO_STATUS_ENCERRADOS, true);
    }
}
