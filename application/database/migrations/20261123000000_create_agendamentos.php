<?php

/**
 * Agendamentos de visita (fork Conser).
 *
 * Cada agendamento cria uma OS para o cliente na data marcada: a OS é a fonte
 * do cliente, do técnico, da data (os.dataInicial/os.dataFinal) e do status,
 * então o agendamento aparece também na agenda do painel e na listagem de OS.
 * A tabela guarda só o que é do agendamento:
 *
 * - os_id: a OS criada (única; apagar a OS apaga o agendamento);
 * - hora: horário combinado com o cliente, opcional;
 * - usuarios_id / criado_em: quem agendou e quando.
 *
 * As permissões vAgendamento, aAgendamento, eAgendamento e dAgendamento são
 * ligadas no grupo Administrador; os outros grupos recebem em Permissões.
 */
class Migration_create_agendamentos extends CI_Migration
{
    private const PERMISSOES = ['vAgendamento', 'aAgendamento', 'eAgendamento', 'dAgendamento'];

    public function up()
    {
        if (! $this->db->table_exists('agendamentos')) {
            $this->dbforge->add_field([
                'idAgendamento' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                // Mesmo tipo de os.idOs (INT(11) com sinal), exigido pela chave estrangeira.
                'os_id' => ['type' => 'INT', 'constraint' => 11],
                'hora' => ['type' => 'TIME', 'null' => true],
                'usuarios_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('idAgendamento', true);
            $this->dbforge->create_table('agendamentos', true, ['ENGINE' => 'InnoDB']);

            // Com db_debug desligado (produção) um CREATE com erro só vai para o log.
            if (! $this->db->table_exists('agendamentos')) {
                throw new RuntimeException('Não foi possível criar a tabela agendamentos; veja o log da aplicação.');
            }

            $tabela = $this->db->protect_identifiers('agendamentos');
            $this->db->query('CREATE UNIQUE INDEX agendamentos_os ON ' . $tabela . ' (os_id)');
            $this->db->query(
                'ALTER TABLE ' . $tabela . ' ADD CONSTRAINT fk_agendamentos_os FOREIGN KEY (os_id) REFERENCES '
                . $this->db->protect_identifiers('os') . ' (idOs) ON DELETE CASCADE ON UPDATE NO ACTION'
            );
        }

        $this->ligarPermissoesDoAdministrador();
    }

    public function down()
    {
        $this->dbforge->drop_table('agendamentos', true);
    }

    /**
     * Liga as permissões novas no grupo Administrador (id 1), que precisa
     * enxergar o módulo logo depois da atualização. O campo guarda JSON (v5)
     * ou o serialize() da v4; o resultado é gravado em JSON, como a tela de
     * permissões faz.
     */
    private function ligarPermissoesDoAdministrador(): void
    {
        $grupo = $this->db->select('permissoes')->where('idPermissao', 1)->get('permissoes')->row();
        if (! $grupo) {
            return;
        }

        $bruto = (string) $grupo->permissoes;
        $mapa = json_decode($bruto, true);
        if (! is_array($mapa)) {
            $mapa = @unserialize($bruto, ['allowed_classes' => false]);
        }
        if (! is_array($mapa)) {
            return;
        }

        foreach (self::PERMISSOES as $codigo) {
            $mapa[$codigo] = 1;
        }

        $this->db->where('idPermissao', 1)->update('permissoes', ['permissoes' => json_encode($mapa)]);
    }
}
