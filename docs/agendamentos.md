# Agendamentos (branch `conser`)

Módulo de agendamento de visitas a clientes. Fica em **Operação → Agendamentos**.

## Como funciona

1. Em **Novo agendamento**, escolha o cliente (o mesmo campo da OS, com cadastro rápido), o técnico, a data
   (atalhos *Em 3 meses*, *Em 6 meses* e *Em 1 ano*), a hora (opcional), o status inicial da OS e o que será feito.
2. Ao salvar, o sistema cria **na hora uma OS** para o cliente, com a data do agendamento como data de entrada e de
   entrega. A OS aparece na **agenda do painel** e na listagem de OS como qualquer outra.
3. A tela **Agendamentos** lista os próximos (padrão), os atrasados (data passada e OS ainda não finalizada,
   faturada nem cancelada) ou todos, com busca por cliente, telefone, serviço ou Nº da OS.
4. **Editar** um agendamento muda cliente, técnico, data, hora, descrição e observações também na OS. O status
   continua sendo mudado na tela da OS.
5. **Excluir** um agendamento apaga a OS junto quando ela ainda não tem nada lançado (itens, equipamentos, anexos,
   anotações, cobrança ou fatura) e o usuário pode excluir OS. Senão, a OS fica e só o agendamento sai.
   Excluir a OS pela tela de OS apaga o agendamento dela.

## Permissões

Novas permissões na matriz de **Configurações → Permissões**, linha *Agendamentos*:
`vAgendamento`, `aAgendamento`, `eAgendamento` e `dAgendamento`. A atualização do banco liga as quatro no grupo
**Administrador**; os outros grupos precisam ser marcados à mão.

Agendar exige também **Adicionar OS** (`aOs`), e editar exige **Editar OS** (`eOs`), porque o agendamento cria e
altera a OS.

## Atualização

Depois de publicar o branch, entre como administrador e use **Configurações → Sistema → Atualizar banco de dados**
(ou `php index.php tools migrate`). A migration `20261123000000_create_agendamentos` cria a tabela `agendamentos`.
