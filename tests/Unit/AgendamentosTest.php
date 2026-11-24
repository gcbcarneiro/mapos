<?php

require_once APPPATH . 'helpers/agendamentos_helper.php';
require_once APPPATH . 'models/Agendamentos_model.php';

/**
 * Agendamentos de visita (fork Conser): conversão do POST, atalhos de data,
 * atraso, valores da tela e o model (criar com a OS, listar por período,
 * excluir com ou sem a OS), além de permissões e menu.
 */
final class AgendamentosTest extends MaposTestCase
{
    private const HOJE = '2026-11-23';

    private const POST = [
        'cliente' => 'Ana Souza',
        'clientes_id' => '3',
        'tecnico' => 'Técnico',
        'usuarios_id' => '1',
        'data' => '2027-11-23',
        'hora' => '14:30',
        'status' => 'Aberto',
        'descricao' => "Manutenção preventiva\nTroca de filtros",
        'observacoes' => 'Portão dos fundos',
        'idOs' => '999',
        'faturado' => '1',
        'os_id' => '55',
    ];

    public function testDadosDeUmPostValido(): void
    {
        [$os, $agendamento, $erros] = agendamentoDadosDoFormulario(self::POST, self::HOJE, true);

        $this->assertSame([], $erros);
        $this->assertSame([
            'clientes_id' => 3,
            'usuarios_id' => 1,
            'dataInicial' => '2027-11-23',
            'dataFinal' => '2027-11-23',
            'descricaoProduto' => "Manutenção preventiva<br>\nTroca de filtros",
            'observacoes' => 'Portão dos fundos',
            'status' => 'Aberto',
        ], $os);
        // Só o que o agendamento grava: nada de os_id, idOs ou faturado do POST.
        $this->assertSame(['hora' => '14:30:00'], $agendamento);
    }

    public function testDataPassadaSoEhAceitaAoEditar(): void
    {
        $post = ['data' => '2026-11-22'] + self::POST;

        [, , $erros] = agendamentoDadosDoFormulario($post, self::HOJE, true);
        $this->assertSame('A data do agendamento não pode ser anterior a hoje.', $erros['data']);

        [$os, , $erros] = agendamentoDadosDoFormulario($post, self::HOJE, false);
        $this->assertSame([], $erros);
        $this->assertSame('2026-11-22', $os['dataFinal']);

        [, , $erros] = agendamentoDadosDoFormulario(['data' => self::HOJE] + self::POST, self::HOJE, true);
        $this->assertSame([], $erros, 'Agendar para hoje é permitido.');
    }

    public function testCamposInvalidos(): void
    {
        [, , $erros] = agendamentoDadosDoFormulario([
            'clientes_id' => 'abc',
            'usuarios_id' => '0',
            'data' => '31/12/2027',
            'hora' => '25:00',
            'status' => 'Faturado',
            'descricao' => '   ',
        ], self::HOJE, true);

        $this->assertSame(['cliente', 'tecnico', 'data', 'hora', 'status', 'descricao'], array_keys($erros));
    }

    public function testEditarNaoMudaOStatusDaOs(): void
    {
        [$os, , $erros] = agendamentoDadosDoFormulario(['status' => 'Cancelado'] + self::POST, self::HOJE, false);

        $this->assertSame([], $erros);
        $this->assertArrayNotHasKey('status', $os);
    }

    public function testHoraOpcional(): void
    {
        $this->assertNull(agendamentoHoraValida(''));
        $this->assertSame('08:05:00', agendamentoHoraValida('08:05'));
        $this->assertSame('08:05:00', agendamentoHoraValida('08:05:59'));
        $this->assertFalse(agendamentoHoraValida('8h'));
        $this->assertSame('14:30', agendamentoHoraCurta('14:30:00'));
        $this->assertSame('', agendamentoHoraCurta(null));
    }

    public function testAtalhosDeDataNoFimDoMes(): void
    {
        $this->assertSame(
            ['Em 3 meses' => '2026-11-30', 'Em 6 meses' => '2027-02-28', 'Em 1 ano' => '2027-08-31'],
            agendamentoAtalhosDeData('2026-08-31')
        );
        $this->assertSame('2029-02-28', agendamentoAtalhosDeData('2028-02-29')['Em 1 ano']);
        $this->assertSame('2027-11-23', agendamentoAtalhosDeData(self::HOJE)['Em 1 ano']);
    }

    public function testAtrasadoSoComOsAberta(): void
    {
        $this->assertTrue(agendamentoAtrasado((object) ['dataFinal' => '2026-11-01', 'status' => 'Aberto'], self::HOJE));
        $this->assertFalse(agendamentoAtrasado((object) ['dataFinal' => '2026-11-01', 'status' => 'Finalizado'], self::HOJE));
        $this->assertFalse(agendamentoAtrasado((object) ['dataFinal' => self::HOJE, 'status' => 'Aberto'], self::HOJE));
    }

    public function testValoresDaTela(): void
    {
        $padrao = agendamentoValoresDoFormulario(null, null, ['status' => 'Aberto', 'tecnico' => 'Eu']);
        $this->assertSame('Aberto', $padrao['status']);
        $this->assertSame('Eu', $padrao['tecnico']);
        $this->assertSame('', $padrao['data']);

        $doPost = agendamentoValoresDoFormulario(['cliente' => ' Ana ', 'hora' => ['x']], null);
        $this->assertSame('Ana', $doPost['cliente']);
        $this->assertSame('', $doPost['hora']);

        $editando = agendamentoValoresDoFormulario(null, (object) [
            'nomeCliente' => 'Ana', 'clientes_id' => 3, 'responsavel' => 'Técnico', 'usuarios_id' => 1,
            'dataFinal' => '2027-01-10', 'hora' => '09:00:00', 'status' => 'Aberto',
            'descricaoProduto' => 'Linha 1<br>' . "\n" . 'Linha 2', 'observacoes' => null,
        ]);
        $this->assertSame('09:00', $editando['hora']);
        $this->assertSame("Linha 1\nLinha 2", $editando['descricao']);
        $this->assertSame('2027-01-10', $editando['data']);
    }

    private function model(): Agendamentos_model
    {
        $this->db->query('CREATE TABLE clientes (idClientes INTEGER PRIMARY KEY AUTOINCREMENT, nomeCliente TEXT, documento TEXT, celular TEXT, telefone TEXT)');
        $this->db->query('CREATE TABLE usuarios (idUsuarios INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT)');
        $this->db->query('CREATE TABLE os (idOs INTEGER PRIMARY KEY AUTOINCREMENT, dataInicial TEXT, dataFinal TEXT, garantia TEXT, descricaoProduto TEXT, defeito TEXT, status TEXT, observacoes TEXT, laudoTecnico TEXT, faturado INTEGER DEFAULT 0, clientes_id INTEGER, usuarios_id INTEGER)');
        $this->db->query('CREATE TABLE agendamentos (idAgendamento INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER, hora TEXT, usuarios_id INTEGER, criado_em TEXT)');
        $this->db->query('CREATE TABLE servicos_os (idServicos_os INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER)');
        $this->db->query('CREATE TABLE os_historico (idHistorico INTEGER PRIMARY KEY AUTOINCREMENT, os_id INTEGER)');

        $this->db->query("INSERT INTO clientes (nomeCliente, documento, celular) VALUES ('Ana Souza', '111', '(11) 99999-0000'), ('Bruno Lima', '222', '')");
        $this->db->query("INSERT INTO usuarios (nome) VALUES ('Técnico')");

        $model = $this->makeInstance(Agendamentos_model::class);
        $this->setPrivateProperty($model, 'db', $this->db);

        return $model;
    }

    private function agendar(Agendamentos_model $model, int $cliente, string $data, string $status = 'Aberto', ?string $hora = null): array
    {
        $criado = $model->criar([
            'clientes_id' => $cliente,
            'usuarios_id' => 1,
            'dataInicial' => $data,
            'dataFinal' => $data,
            'descricaoProduto' => 'Visita',
            'observacoes' => '',
            'status' => $status,
        ], ['hora' => $hora], 1);
        $this->assertNotNull($criado);

        return $criado;
    }

    public function testCriarGravaOsEAgendamentoJuntos(): void
    {
        $model = $this->model();
        [$idAgendamento, $idOs] = $this->agendar($model, 1, '2027-11-23', 'Aberto', '14:30:00');

        $os = $this->db->get_where('os', ['idOs' => $idOs])->row();
        $this->assertSame('2027-11-23', $os->dataInicial);
        $this->assertSame('2027-11-23', $os->dataFinal);
        $this->assertSame('0', (string) $os->faturado);

        $linha = $model->getById($idAgendamento);
        $this->assertSame('Ana Souza', $linha->nomeCliente);
        $this->assertSame('Técnico', $linha->responsavel);
        $this->assertSame('14:30:00', $linha->hora);
        $this->assertSame($idOs, (int) $linha->os_id);
        $this->assertNull($model->getById(999));
    }

    public function testListarPorPeriodoEPesquisa(): void
    {
        $model = $this->model();
        [$futuro] = $this->agendar($model, 1, '2027-01-10');
        [$hoje] = $this->agendar($model, 2, self::HOJE);
        [$atrasado] = $this->agendar($model, 2, '2026-10-01');
        [$feito] = $this->agendar($model, 1, '2026-09-01', 'Finalizado');

        $ids = static fn (array $linhas) => array_map('intval', array_column($linhas, 'idAgendamento'));

        $this->assertSame([$hoje, $futuro], $ids($model->listar([], self::HOJE, 10, 0)), 'Próximos, do mais perto ao mais longe.');
        $this->assertSame([$atrasado], $ids($model->listar(['periodo' => 'atrasados'], self::HOJE, 10, 0)));
        $this->assertSame([$futuro, $hoje, $atrasado, $feito], $ids($model->listar(['periodo' => 'todos'], self::HOJE, 10, 0)));
        $this->assertSame([$futuro, $feito], $ids($model->listar(['periodo' => 'todos', 'pesquisa' => 'ana'], self::HOJE, 10, 0)));
        $this->assertSame([$futuro, $feito], $ids($model->listar(['periodo' => 'todos', 'pesquisa' => '99999'], self::HOJE, 10, 0)), 'Busca pelo celular da Ana.');
        $this->assertSame([$hoje], $ids($model->listar(['periodo' => 'todos', 'de' => self::HOJE, 'ate' => '2026-12-31'], self::HOJE, 10, 0)));
        $this->assertSame(2, $model->contar([], self::HOJE));
        $this->assertSame(4, $model->contar(['periodo' => 'todos'], self::HOJE));
    }

    public function testPesquisaNaoViraSql(): void
    {
        $model = $this->model();
        $this->agendar($model, 1, '2027-01-10');

        $this->assertSame([], $model->listar(['periodo' => 'todos', 'pesquisa' => "x' OR '1'='1"], self::HOJE, 10, 0));
    }

    public function testAtualizarMexeNaOsENoAgendamento(): void
    {
        $model = $this->model();
        [$id, $idOs] = $this->agendar($model, 1, '2027-01-10');

        $this->assertTrue($model->atualizar($id, $idOs, ['clientes_id' => 2, 'dataInicial' => '2027-02-01', 'dataFinal' => '2027-02-01'], ['hora' => '08:00:00']));

        $linha = $model->getById($id);
        $this->assertSame('Bruno Lima', $linha->nomeCliente);
        $this->assertSame('2027-02-01', $linha->dataFinal);
        $this->assertSame('08:00:00', $linha->hora);
    }

    public function testExcluirLevaAOsSoQuandoElaEstaIntacta(): void
    {
        $model = $this->model();
        [$intacto, $osIntacta] = $this->agendar($model, 1, '2027-01-10');
        [$comServico, $osComServico] = $this->agendar($model, 2, '2027-01-11');
        [, $osFinalizada] = $this->agendar($model, 2, '2027-01-12', 'Finalizado');
        $this->db->insert('servicos_os', ['os_id' => $osComServico]);
        $this->db->insert('os_historico', ['os_id' => $osIntacta]);

        $this->assertTrue($model->osIntacta($osIntacta));
        $this->assertFalse($model->osIntacta($osComServico));
        $this->assertFalse($model->osIntacta($osFinalizada));
        $this->assertFalse($model->osIntacta(999));

        $this->assertTrue($model->excluirComOs($intacto, $osIntacta));
        $this->assertNull($model->getById($intacto));
        $this->assertSame(0, $this->db->where('idOs', $osIntacta)->count_all_results('os'));
        $this->assertSame(0, $this->db->where('os_id', $osIntacta)->count_all_results('os_historico'));

        $this->assertTrue($model->excluir($comServico));
        $this->assertNull($model->getById($comServico));
        $this->assertSame(1, $this->db->where('idOs', $osComServico)->count_all_results('os'), 'A OS com serviço lançado fica.');
    }

    public function testPermissoesNaMatrizEMenu(): void
    {
        foreach (['vAgendamento', 'aAgendamento', 'eAgendamento', 'dAgendamento'] as $codigo) {
            $this->assertContains($codigo, permissoesCodigos());
        }

        $permite = static fn (array $permissoes) => static function ($controller, $metodo) use ($permissoes) {
            return strcasecmp($controller, 'Agendamentos') !== 0 || in_array('vAgendamento', $permissoes, true);
        };

        $rotulos = static fn (array $menu) => array_column($menu, 'label');
        $this->assertContains('Agendamentos', $rotulos(layoutMenuVisivel(layoutMenu(), $permite(['vAgendamento']), 'Mapos', 'index')));
        $this->assertNotContains('Agendamentos', $rotulos(layoutMenuVisivel(layoutMenu(), $permite([]), 'Mapos', 'index')));
    }

    public function testResumoParaOMotorista(): void
    {
        $os = (object) [
            'idOs' => 24, 'dataInicial' => '2026-11-26', 'dataFinal' => '2026-11-26',
            'nomeCliente' => 'Condominio Pedro Kassouf', 'contato_cliente' => 'FABIO',
            'celular_cliente' => '(21) 98051-9671', 'telefone_cliente' => '(21) 98051-9671',
            'rua' => 'Rua Hildegardo De Noronha', 'numero' => '1205', 'complemento' => '  Portão azul ',
            'bairro' => 'Alto', 'cidade' => 'Teresópolis', 'estado' => 'RJ', 'cep' => '25961-260', 'nome' => 'Luciene',
        ];

        $resumo = osResumoMotorista($os, '08:00:00');

        $this->assertSame('0024', $resumo['os']);
        $this->assertSame('26/11/2026 às 08:00', $resumo['quando']);
        $this->assertSame('FABIO · (21) 98051-9671', $resumo['contato'], 'Telefone repetido aparece uma vez.');
        $this->assertSame('Rua Hildegardo De Noronha, 1205, Alto, Teresópolis - RJ · CEP 25961-260', $resumo['endereco']);
        $this->assertSame('Portão azul', $resumo['referencia']);
        $this->assertSame('Luciene', $resumo['responsavel']);
        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=Rua%20Hildegardo', $resumo['mapa']);

        $qr = osQrCodeDoMapa($resumo['mapa']);
        $this->assertNotNull($qr);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr);
        $this->assertNull(osQrCodeDoMapa(''));
    }

    public function testResumoSemEnderecoNemHora(): void
    {
        $resumo = osResumoMotorista((object) ['idOs' => 7, 'dataInicial' => '2026-11-26', 'dataFinal' => null, 'nomeCliente' => 'Ana']);

        $this->assertSame('26/11/2026', $resumo['quando']);
        $this->assertSame('', $resumo['endereco']);
        $this->assertSame('', $resumo['mapa']);
        $this->assertSame('', $resumo['contato']);
    }
}
