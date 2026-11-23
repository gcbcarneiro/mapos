<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Agendamentos de visita (fork Conser): o usuário escolhe o cliente e a data
 * futura, e o agendamento cria na hora uma OS para esse cliente com a data
 * marcada. A OS aparece na agenda do painel e na listagem de OS; a tela de
 * agendamentos lista os próximos, os atrasados e todos.
 *
 * Telas no padrão da v5: listagem como Clientes::gerenciar() e formulário
 * como Clientes::formulario() (AGENTS.md).
 */
class Agendamentos extends MY_Controller
{
    /** Filtros da listagem, na query string (listagemFiltros()). */
    public const FILTROS = [
        'pesquisa' => 'texto',
        'periodo' => ['proximos', 'atrasados', 'todos'],
        'de' => 'texto',
        'ate' => 'texto',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'os', 'agendamentos']);
        $this->load->model('agendamentos_model');
        $this->load->model('os_model');
        $this->data['menuAgendamentos'] = 'Agendamentos';
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vAgendamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar agendamentos.');
            redirect(base_url());
        }

        $hoje = date('Y-m-d');
        $filtros = $this->filtrosDaListagem();
        $offset = (int) $this->uri->segment(3);
        $total = $this->agendamentos_model->contar($filtros, $hoje);

        $this->data['filtros'] = $filtros;
        $this->data['hoje'] = $hoje;
        $this->data['total'] = $total;
        $this->data['results'] = $this->agendamentos_model->listar($filtros, $hoje, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('agendamentos/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aAgendamento') && $this->permite('aOs'),
            'editar' => $this->permite('eAgendamento') && $this->permite('eOs'),
            'excluir' => $this->permite('dAgendamento'),
            'ver_os' => $this->permite('vOs'),
        ];
        $this->data['controle_editos'] = ($this->data['configuration']['control_editos'] ?? '0') == '1';

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo agendamento', 'icon' => 'plus', 'href' => site_url('agendamentos/adicionar')];
        }

        $this->data['view'] = 'agendamentos/agendamentos';

        return $this->layout();
    }

    /**
     * Filtros válidos: pesquisa, periodo e as datas de/ate em AAAA-MM-DD; data
     * inválida é descartada. Sem periodo, a tela mostra os próximos.
     *
     * @return array<string, string>
     */
    private function filtrosDaListagem(): array
    {
        $filtros = listagemFiltros(self::FILTROS, $this->input->get());

        foreach (['de', 'ate'] as $data) {
            if (isset($filtros[$data])) {
                $valida = dataIsoParaYmd($filtros[$data]);
                if ($valida === null) {
                    unset($filtros[$data]);
                } else {
                    $filtros[$data] = $valida;
                }
            }
        }

        return $filtros;
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aAgendamento')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'aOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para agendar: é preciso poder adicionar agendamentos e O.S.');
            redirect(base_url());
        }

        return $this->formulario(null);
    }

    public function editar()
    {
        $agendamento = is_numeric($this->uri->segment(3)) ? $this->agendamentos_model->getById((int) $this->uri->segment(3)) : null;
        if (! $agendamento) {
            $this->session->set_flashdata('error', 'Agendamento não encontrado ou parâmetro inválido.');
            redirect('agendamentos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eAgendamento')
            || ! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar agendamentos.');
            redirect(base_url());
        }

        if (! $this->os_model->isEditable((int) $agendamento->os_id)) {
            $this->session->set_flashdata('error', 'A OS deste agendamento está faturada ou cancelada e não pode mais ser alterada.');
            redirect('agendamentos');
        }

        return $this->formulario($agendamento);
    }

    /**
     * Formulário do agendamento (padrão dos formulários da v5): POST comum,
     * validação no servidor com os erros em cada campo e redirecionamento
     * com toast. O id editado vem da URL, nunca do POST.
     */
    private function formulario(?object $agendamento)
    {
        $novo = $agendamento === null;
        $hoje = date('Y-m-d');
        $erros = [];

        if ($this->input->method() === 'post') {
            [$os, $dadosAgendamento, $erros] = agendamentoDadosDoFormulario((array) $this->input->post(), $hoje, $novo);

            if ($erros === []) {
                if (! $this->os_model->existe('clientes', 'idClientes', (int) $os['clientes_id'])) {
                    $erros['cliente'] = 'Escolha um cliente da lista.';
                }
                if (! $this->os_model->existe('usuarios', 'idUsuarios', (int) $os['usuarios_id'], true)) {
                    $erros['tecnico'] = 'Escolha um técnico ativo da lista.';
                }
            }

            if ($erros === []) {
                if ($novo) {
                    $criado = $this->agendamentos_model->criar($os, $dadosAgendamento, $this->usuarioLogado());
                    if ($criado !== null) {
                        [$idAgendamento, $idOs] = $criado;
                        $this->os_model->registrarStatus($idOs, null, (string) $os['status'], $this->usuarioLogado());
                        log_info('Adicionou um agendamento. ID: ' . $idAgendamento . ' (OS ' . $idOs . ')');
                        $this->session->set_flashdata('success', 'Agendamento salvo para ' . dataBr($os['dataFinal']) . ' e OS #' . $idOs . ' criada.');

                        return redirect('agendamentos');
                    }
                } else {
                    $idAgendamento = (int) $agendamento->idAgendamento;
                    if ($this->agendamentos_model->atualizar($idAgendamento, (int) $agendamento->os_id, $os, $dadosAgendamento)) {
                        log_info('Alterou um agendamento. ID: ' . $idAgendamento . ' (OS ' . (int) $agendamento->os_id . ')');
                        $this->session->set_flashdata('success', 'Agendamento alterado. A OS #' . (int) $agendamento->os_id . ' foi atualizada.');

                        return redirect('agendamentos/editar/' . $idAgendamento);
                    }
                }

                $erros['_geral'] = 'Não foi possível salvar. Tente de novo.';
            }
        }

        $this->data['agendamento'] = $agendamento;
        $this->data['valores'] = agendamentoValoresDoFormulario(
            $this->input->method() === 'post' ? (array) $this->input->post() : null,
            $agendamento,
            [
                'tecnico' => (string) $this->session->userdata('nome_admin'),
                'usuarios_id' => (string) $this->session->userdata('id_admin'),
                'status' => 'Aberto',
            ]
        );
        $this->data['erros'] = $erros;
        $this->data['hoje'] = $hoje;
        $this->data['atalhos'] = agendamentoAtalhosDeData($hoje);
        $this->data['pode'] = [
            'cadastrar_cliente' => $this->permite('aCliente'),
            'ver_os' => ! $novo && $this->permite('vOs'),
        ];
        $this->data['view'] = 'agendamentos/formulario';

        return $this->layout();
    }

    private function usuarioLogado(): ?int
    {
        $id = (int) $this->session->userdata('id_admin');

        return $id > 0 ? $id : null;
    }

    /**
     * Exclui o agendamento (POST com o token CSRF). A OS criada por ele sai
     * junto quando ainda não tem nada lançado e o usuário pode excluir OS;
     * senão ela fica, e o aviso diz o número.
     */
    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dAgendamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir agendamentos.');
            redirect(base_url());
        }

        $listagem = site_url('agendamentos') . listagemQuery($this->filtrosDaListagem());
        $id = (int) $this->input->post('id');
        $agendamento = $id > 0 ? $this->agendamentos_model->getById($id) : null;
        if (! $agendamento) {
            $this->session->set_flashdata('error', 'Agendamento não encontrado.');
            redirect($listagem);
        }

        $idOs = (int) $agendamento->os_id;
        if ($this->permite('dOs') && $this->agendamentos_model->osIntacta($idOs)) {
            if ($this->agendamentos_model->excluirComOs($id, $idOs)) {
                log_info('Removeu um agendamento e a OS dele. ID: ' . $id . ' (OS ' . $idOs . ')');
                $this->session->set_flashdata('success', 'Agendamento e OS #' . $idOs . ' excluídos.');
            } else {
                $this->session->set_flashdata('error', 'Não foi possível excluir o agendamento. Tente de novo.');
            }

            redirect($listagem);
        }

        if ($this->agendamentos_model->excluir($id)) {
            log_info('Removeu um agendamento. ID: ' . $id . ' (OS ' . $idOs . ' mantida)');
            $this->session->set_flashdata('success', 'Agendamento excluído. A OS #' . $idOs . ' foi mantida porque já tem informações lançadas (ou você não pode excluir OS).');
        } else {
            $this->session->set_flashdata('error', 'Não foi possível excluir o agendamento. Tente de novo.');
        }

        redirect($listagem);
    }
}
