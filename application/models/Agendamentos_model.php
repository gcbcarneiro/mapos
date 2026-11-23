<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Agendamentos de visita (fork Conser). Cada agendamento aponta para a OS que
 * ele criou; cliente, técnico, data e status vêm da OS.
 */
class Agendamentos_model extends CI_Model
{
    /** Colunas da linha de agendamento (listagem e formulário). */
    private const COLUNAS = 'agendamentos.idAgendamento, agendamentos.os_id, agendamentos.hora, agendamentos.criado_em,'
        . ' os.idOs, os.clientes_id, os.usuarios_id, os.dataInicial, os.dataFinal, os.status, os.faturado, os.descricaoProduto, os.observacoes,'
        . ' clientes.nomeCliente, clientes.celular, clientes.telefone, usuarios.nome AS responsavel';

    /**
     * Agendamentos da listagem, na ordem da data (e da hora). Próximos e
     * atrasados vêm do mais perto ao mais longe de hoje; "todos", do mais
     * recente ao mais antigo.
     *
     * @param  array<string, string>  $filtros  pesquisa, periodo, de, ate (já validados)
     */
    public function listar(array $filtros, string $hoje, int $limite, int $offset): array
    {
        $this->db->select(self::COLUNAS);
        $this->aplicarFiltros($filtros, $hoje);

        $direcao = ($filtros['periodo'] ?? 'proximos') === 'proximos' ? 'asc' : 'desc';

        return $this->db
            ->order_by('os.dataFinal', $direcao)
            ->order_by('agendamentos.hora', $direcao)
            ->order_by('agendamentos.idAgendamento', $direcao)
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();
    }

    /** Total da listagem com os mesmos filtros de listar(). */
    public function contar(array $filtros, string $hoje): int
    {
        $this->aplicarFiltros($filtros, $hoje);

        return (int) $this->db->count_all_results();
    }

    /**
     * pesquisa procura no nome, documento e telefones do cliente, na descrição
     * do serviço e, se for número, no Nº da OS. periodo: proximos (de hoje em
     * diante, padrão), atrasados (data passada e OS ainda aberta) ou todos.
     * de e ate limitam a data do agendamento.
     */
    private function aplicarFiltros(array $filtros, string $hoje): void
    {
        $this->db->from('agendamentos');
        $this->db->join('os', 'os.idOs = agendamentos.os_id');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id', 'left');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id', 'left');

        $pesquisa = $filtros['pesquisa'] ?? '';
        if ($pesquisa !== '') {
            $this->db->group_start()
                ->like('clientes.nomeCliente', $pesquisa)
                ->or_like('clientes.documento', $pesquisa)
                ->or_like('clientes.celular', $pesquisa)
                ->or_like('clientes.telefone', $pesquisa)
                ->or_like('os.descricaoProduto', $pesquisa);
            if (ctype_digit($pesquisa)) {
                $this->db->or_where('os.idOs', (int) $pesquisa);
            }
            $this->db->group_end();
        }

        switch ($filtros['periodo'] ?? 'proximos') {
            case 'proximos':
                $this->db->where('os.dataFinal >=', $hoje);
                break;
            case 'atrasados':
                $this->db->where('os.dataFinal <', $hoje);
                $this->db->where_not_in('os.status', AGENDAMENTO_STATUS_ENCERRADOS);
                break;
        }

        if (($filtros['de'] ?? '') !== '') {
            $this->db->where('os.dataFinal >=', $filtros['de']);
        }
        if (($filtros['ate'] ?? '') !== '') {
            $this->db->where('os.dataFinal <=', $filtros['ate']);
        }
    }

    public function getById(int $id): ?object
    {
        $linha = $this->db
            ->select(self::COLUNAS)
            ->from('agendamentos')
            ->join('os', 'os.idOs = agendamentos.os_id')
            ->join('clientes', 'clientes.idClientes = os.clientes_id', 'left')
            ->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id', 'left')
            ->where('agendamentos.idAgendamento', $id)
            ->limit(1)
            ->get()
            ->row();

        return $linha ?: null;
    }

    /**
     * Cria a OS e o agendamento juntos (os dois ou nenhum).
     *
     * @param  array<string, mixed>  $os           agendamentoDadosDoFormulario()[0]
     * @param  array<string, mixed>  $agendamento  agendamentoDadosDoFormulario()[1]
     * @return array{0: int, 1: int}|null [idAgendamento, idOs]
     */
    public function criar(array $os, array $agendamento, ?int $usuario): ?array
    {
        $this->db->trans_start();

        $this->db->insert('os', $os + ['faturado' => 0, 'garantia' => '', 'defeito' => '', 'laudoTecnico' => '']);
        $idOs = (int) $this->db->insert_id();

        $idAgendamento = 0;
        if ($idOs > 0) {
            $this->db->insert('agendamentos', $agendamento + [
                'os_id' => $idOs,
                'usuarios_id' => $usuario,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);
            $idAgendamento = (int) $this->db->insert_id();
        }

        $this->db->trans_complete();

        return $this->db->trans_status() && $idOs > 0 && $idAgendamento > 0 ? [$idAgendamento, $idOs] : null;
    }

    /** Grava as alterações na OS e no agendamento juntos. */
    public function atualizar(int $idAgendamento, int $idOs, array $os, array $agendamento): bool
    {
        $this->db->trans_start();
        $this->db->where('idOs', $idOs)->update('os', $os);
        $this->db->where('idAgendamento', $idAgendamento)->update('agendamentos', $agendamento);
        $this->db->trans_complete();

        return (bool) $this->db->trans_status();
    }

    /**
     * A OS ainda é só o agendamento: aberta pelo agendamento e sem nada
     * lançado nela (itens, equipamentos, anexos, anotações, cobrança ou
     * fatura). Só assim ela sai junto quando o agendamento é excluído.
     */
    public function osIntacta(int $idOs): bool
    {
        $os = $this->db->select('faturado, status')->where('idOs', $idOs)->get('os')->row();
        if (! $os || (int) $os->faturado === 1 || in_array($os->status, ['Finalizado', 'Faturado'], true)) {
            return false;
        }

        foreach (['produtos_os', 'servicos_os', 'equipamentos_os', 'anexos', 'anotacoes_os', 'cobrancas'] as $tabela) {
            if ($this->db->table_exists($tabela) && $this->db->where('os_id', $idOs)->count_all_results($tabela) > 0) {
                return false;
            }
        }

        return true;
    }

    /** Exclui só o agendamento; a OS continua. */
    public function excluir(int $idAgendamento): bool
    {
        return (bool) $this->db->where('idAgendamento', $idAgendamento)->delete('agendamentos');
    }

    /** Exclui o agendamento e a OS que ele criou (use depois de osIntacta()). */
    public function excluirComOs(int $idAgendamento, int $idOs): bool
    {
        $this->db->trans_start();
        $this->db->where('idAgendamento', $idAgendamento)->delete('agendamentos');
        if ($this->db->table_exists('os_historico')) {
            $this->db->where('os_id', $idOs)->delete('os_historico');
        }
        $this->db->where('idOs', $idOs)->delete('os');
        $this->db->trans_complete();

        return (bool) $this->db->trans_status();
    }
}
