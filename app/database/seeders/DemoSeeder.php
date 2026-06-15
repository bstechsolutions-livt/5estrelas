<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\Comercial\Proposta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    private array $firstNames = [
        'Bruno', 'Ana', 'Carlos', 'Juliana', 'Fernanda', 'Rafael', 'Mariana', 'Lucas',
        'Camila', 'Pedro', 'Beatriz', 'Felipe', 'Larissa', 'Gabriel', 'Patrícia',
        'Rodrigo', 'Aline', 'Thiago', 'Carolina', 'André', 'Daniela', 'Marcos',
        'Renata', 'Gustavo', 'Letícia', 'Vinicius', 'Amanda', 'Diego', 'Bianca', 'Eduardo',
    ];

    private array $lastNames = [
        'Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Lima', 'Costa', 'Ribeiro',
        'Almeida', 'Carvalho', 'Gomes', 'Martins', 'Araújo', 'Nascimento', 'Barbosa',
        'Cavalcanti', 'Rocha', 'Andrade', 'Mendes', 'Cardoso', 'Teixeira', 'Moreira',
    ];

    private array $highlightTitles = [
        'Comunicado Interno', 'Vale Compras', 'Aniversariantes', 'Treinamento',
        'Campanha de Vendas', 'Evento Mensal', 'Reconhecimento', 'Novidades RH',
        'Resultado Q1', 'Confraternização', 'Programa Bem-Estar', 'Premiação',
    ];

    private array $newsTitles = [
        'Novo benefício para colaboradores',
        'Resultados do trimestre superam expectativas',
        'Programa de capacitação inicia em junho',
        'Inauguração da nova sede em São Paulo',
        'Conheça os novos integrantes da equipe',
        'Política de home office atualizada',
        'Campanha do agasalho 2026',
        'Reconhecimento: colaboradores destaque',
        'Resultados da pesquisa de clima',
        'Calendário de feriados do segundo semestre',
        'Atualização do plano de carreira',
        'Programa de indicação de talentos',
    ];

    private array $newsContents = [
        'Estamos felizes em anunciar uma novidade importante para todos os colaboradores. Nos próximos dias mais detalhes serão divulgados pelos nossos canais oficiais.',
        'Os números mostram um trimestre histórico, com crescimento expressivo em todas as áreas. Agradecemos o empenho e dedicação de toda a equipe.',
        'Inscrições abertas até o final do mês. Os interessados devem se cadastrar no portal interno e aguardar a confirmação por e-mail.',
        'Após meses de planejamento, finalmente abrimos as portas da nova sede. Todos os colaboradores estão convidados para o coffee de inauguração.',
        'Damos as boas-vindas aos novos colegas que ingressaram este mês. Sucesso na nova jornada e contem com todos nós!',
        'Confiram a política revisada disponível na intranet. Em caso de dúvidas, procurem o departamento de Gente e Gestão.',
        'A campanha vai até o final de junho. Os pontos de coleta estão espalhados em todas as unidades. Sua doação faz a diferença.',
        'Parabéns aos colaboradores reconhecidos no programa de excelência deste mês. Seu trabalho é uma inspiração para todos nós.',
        'Os resultados serão apresentados em reunião geral. Acompanhem as próximas comunicações para detalhes do encontro.',
        'O calendário oficial já está disponível no portal. Programem-se com antecedência e aproveitem os feriados prolongados.',
        'O plano foi revisado para oferecer mais oportunidades de crescimento. Falem com seus líderes para entender as novas trilhas.',
        'Indique alguém para nosso time e ganhe bonificações exclusivas. Mais informações no portal de RH.',
    ];

    public function run(): void
    {
        // Loga como admin para que toda auditoria tenha um responsável
        $admin = User::where('email', 'admin@5estrelas.com.br')->first();
        if ($admin) {
            auth()->setUser($admin);
        }

        // 1. Criar usuários demo
        $this->command->info('Criando usuários demo...');
        $usersCreated = $this->createUsers(20);

        // 2. Criar posts (destaques + notícias)
        $this->command->info('Criando destaques e notícias...');
        $highlights = $this->createPosts(Post::TYPE_HIGHLIGHT, count($this->highlightTitles), $usersCreated);
        $news = $this->createPosts(Post::TYPE_NEWS, count($this->newsTitles), $usersCreated);

        // 3. Criar likes e comentários
        $this->command->info('Distribuindo likes e comentários...');
        $allPosts = $highlights->merge($news);
        $allUsers = User::all();

        $this->createInteractions($allPosts, $allUsers);

        // 4. Criar propostas comerciais (snapshot de cotações)
        $this->command->info('Criando propostas comerciais...');
        $this->createPropostas($admin ?? User::first());

        // Limpa autenticação
        auth()->logout();

        $this->command->info('✅ Demo seeder concluído!');
    }

    private function createUsers(int $count): \Illuminate\Support\Collection
    {
        $created = collect();
        $usedEmails = [];

        // Permissões padrão pra distribuir entre usuários comuns
        $allPermKeys = ['usuarios.listar', 'aparencia.editar', 'noticias.gerenciar', 'auditoria.visualizar'];
        $permIds = Permission::whereIn('key', $allPermKeys)->pluck('id', 'key');

        for ($i = 0; $i < $count; $i++) {
            $first = $this->firstNames[array_rand($this->firstNames)];
            $last = $this->lastNames[array_rand($this->lastNames)];
            $name = "{$first} {$last}";
            $email = strtolower(Str::ascii($first) . '.' . Str::ascii($last) . random_int(1, 99) . '@5estrelas.com.br');

            if (in_array($email, $usedEmails, true)) {
                continue;
            }
            $usedEmails[] = $email;

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'is_active' => random_int(0, 9) > 0, // 90% ativos
            ]);

            // Avatar via pravatar
            $avatarPath = $this->downloadImage("https://i.pravatar.cc/256?u={$email}", "avatars/{$user->id}_demo.jpg");
            if ($avatarPath) {
                $user->avatar_path = $avatarPath;
                $user->saveQuietly();
            }

            // Distribui aleatoriamente algumas permissões em ~30% dos usuários
            if (random_int(0, 9) < 3) {
                $randomPerms = collect($allPermKeys)->random(random_int(1, count($allPermKeys)));
                $ids = $permIds->only($randomPerms->all())->values();
                $user->permissions()->syncWithoutDetaching($ids);
            }

            $created->push($user);
        }

        return $created;
    }

    private function createPosts(string $type, int $count, \Illuminate\Support\Collection $users): \Illuminate\Support\Collection
    {
        $titles = $type === Post::TYPE_HIGHLIGHT ? $this->highlightTitles : $this->newsTitles;
        $contents = $this->newsContents;
        $created = collect();

        for ($i = 0; $i < $count; $i++) {
            $creator = $users->random();
            $title = $titles[$i % count($titles)] . ' #' . ($i + 1);

            // Loga o creator pra auditoria registrar o user correto
            auth()->setUser($creator);

            $post = Post::create([
                'type' => $type,
                'title' => $title,
                'content' => $contents[array_rand($contents)],
                'published_at' => now()->subHours(random_int(1, 240)),
                'expires_at' => null,
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            // Imagem
            $size = $type === Post::TYPE_HIGHLIGHT ? '1080/1080' : '1080/1350';
            $seed = "demo-{$type}-{$post->id}";
            $imagePath = $this->downloadImage(
                "https://picsum.photos/seed/{$seed}/{$size}",
                "posts/{$type}_{$post->id}_demo.jpg"
            );

            if ($imagePath) {
                $post->image_path = $imagePath;
                $post->saveQuietly();
            }

            $created->push($post);
        }

        return $created;
    }

    private function createInteractions(\Illuminate\Support\Collection $posts, \Illuminate\Support\Collection $users): void
    {
        $sampleComments = [
            'Muito bom! 👏',
            'Parabéns equipe!',
            'Ótima notícia, obrigado pela divulgação',
            'Top demais 🚀',
            'Excelente trabalho de todos',
            'Quando teremos mais detalhes?',
            'Adorei a iniciativa',
            'Vou compartilhar com o time',
            'Gostei muito disso',
            'Parabéns a todos os envolvidos!',
            'Show de bola',
            'Sensacional!',
            'Que orgulho da equipe',
            'Mal posso esperar pelos próximos passos',
            'Bem legal essa novidade',
        ];

        foreach ($posts as $post) {
            // Likes: entre 0 e 60% dos users
            $maxLikes = (int) ($users->count() * 0.6);
            $likeCount = random_int(0, $maxLikes);
            if ($likeCount > 0) {
                $likers = $users->random($likeCount)->pluck('id');
                foreach ($likers as $userId) {
                    DB::table('post_likes')->insertOrIgnore([
                        'post_id' => $post->id,
                        'user_id' => $userId,
                        'created_at' => now()->subMinutes(random_int(1, 4320)),
                    ]);
                }
            }

            // Comentários: entre 0 e 8 por post
            $commentCount = random_int(0, 8);
            for ($j = 0; $j < $commentCount; $j++) {
                $user = $users->random();
                // Loga o usuário pra que a auditoria registre o user_name corretamente
                auth()->setUser($user);

                PostComment::create([
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'content' => $sampleComments[array_rand($sampleComments)],
                    'created_at' => now()->subMinutes(random_int(1, 2880)),
                    'updated_at' => now()->subMinutes(random_int(1, 2880)),
                ]);
            }
        }

        // Limpa o usuário logado ao final
        auth()->logout();
    }

    /**
     * Cria propostas comerciais demo (snapshot de cotações).
     * Idempotência: limpa a tabela antes de semear (truncate), pois numero é sequencial/único.
     */
    private function createPropostas(?User $author): void
    {
        // Reset idempotente: a numeração é sequencial e única, então zeramos antes.
        Proposta::query()->delete();

        if ($author) {
            auth()->setUser($author);
        }

        $clientes = [
            'Condomínio Residencial Jardins', 'Shopping Center Norte', 'Hospital São Lucas',
            'Banco Regional do Brasil', 'Faculdade Horizonte', 'Indústria Metalúrgica Souza',
            'Supermercados Bom Preço', 'Prefeitura de Valparaíso', 'Centro Empresarial Alvorada',
            'Clínica Vida Plena', 'Distribuidora Central LTDA', 'Colégio Saber',
            'Resort Águas Claras', 'Terminal Rodoviário Sul', 'Edifício Comercial Platinum',
        ];
        $empresas = ['seg-df', 'seg-go', 'seg-mt', 'seg-mg', 'seg-sp', 'apoio-df'];
        $modelos = ['5estrelas', 'in05'];
        $status = ['rascunho', 'enviada', 'aprovada', 'reprovada'];
        $periodicidades = ['Mensal', 'Anual'];
        $ccts = ['SINDESP-DF 2026', 'FETHE/MG 2026', 'SINDESP-GO 2026', 'SEAC-SP 2026'];
        $categorias = ['Vigilante', 'Agente de Portaria', 'Controlador de Acesso', 'Vigilante (Motorizado)'];
        $escalasNomes = ['12x36 — Diurno', '12x36 — Noturno', '24 Horas (12x36)', '44h — 5×2'];

        $total = random_int(8, 15);

        for ($i = 0; $i < $total; $i++) {
            $cliente = $clientes[$i % count($clientes)];
            $modelo = $modelos[array_rand($modelos)];

            // Monta 1 a 4 postos de exemplo coerentes
            $qtdItens = random_int(1, 4);
            $postos = [];
            $totalMensal = 0;
            $qtdPostos = 0;
            $qtdFunc = 0;
            $vaTotal = 0;

            for ($j = 0; $j < $qtdItens; $j++) {
                $unit = round(random_int(450000, 1200000) / 100, 2); // R$ 4.500 a 12.000
                $postosQtd = random_int(1, 6);
                $funcPosto = [1, 2, 4][array_rand([1, 2, 4])];
                $vaUnit = round(random_int(2000, 9000) / 100, 2);

                $postos[] = [
                    'id' => $j + 1,
                    'cat' => $categorias[array_rand($categorias)],
                    'catIcone' => 'shield',
                    'escala' => $escalasNomes[array_rand($escalasNomes)],
                    'funcPosto' => $funcPosto,
                    'qtdPostos' => $postosQtd,
                    'descr' => '',
                    'unitVal' => $unit,
                    'totalMensal' => round($unit * $postosQtd, 2),
                    'vaUnit' => $vaUnit,
                    'modelo' => $modelo,
                ];

                $totalMensal += $unit * $postosQtd;
                $qtdPostos += $postosQtd;
                $qtdFunc += $postosQtd * $funcPosto;
                $vaTotal += $vaUnit * $postosQtd;
            }

            $totalMensal = round($totalMensal, 2);
            $vaTotal = round($vaTotal, 2);
            $empresa = $empresas[array_rand($empresas)];
            $periodicidade = $periodicidades[array_rand($periodicidades)];
            $cct = $ccts[array_rand($ccts)];
            $data = now()->subDays(random_int(5, 240));
            $numero = Proposta::gerarNumero();

            // ── Campos do "Controle de Propostas" (funil/KPIs) ──
            // Distribuição realista de situações para o funil ter volume em cada coluna.
            $situacoes = ['EM ANÁLISE', 'EM ANÁLISE', 'APROVADO', 'APROVADO', 'REPROVADO', 'ESTIMATIVA', 'REDUÇÃO'];
            $situacao = $situacoes[array_rand($situacoes)];
            $servicos = implode(', ', array_values(array_unique(array_map(
                fn ($p) => (string) ($p['cat'] ?? ''),
                $postos,
            ))));
            $contatos = ['Maria Souza', 'João Pereira', 'Ana Lima', 'Carlos Mendes', 'Patrícia Rocha', 'Rafael Alves'];
            $valorAprovado = null;
            $dataAprovacao = null;
            if ($situacao === 'APROVADO') {
                // Valor aprovado fica entre 90% e 100% do proposto (negociação).
                $valorAprovado = round($totalMensal * (random_int(90, 100) / 100), 2);
                $dataAprovacao = (clone $data)->addDays(random_int(3, 25))->toDateString();
            }

            Proposta::create([
                'numero' => $numero,
                'cliente' => $cliente,
                'empresa' => $empresa,
                'modelo' => $modelo,
                'periodicidade' => $periodicidade,
                'cct' => $cct,
                'data_proposta' => $data->toDateString(),
                'status' => $status[array_rand($status)],
                // Controle de Propostas
                'revisao' => random_int(0, 4) === 0 ? 'Rev.01' : 'N/A',
                'situacao' => $situacao,
                'servicos' => $servicos,
                'posto' => $modelo === 'in05' ? 'IN 05' : 'Modelo 5 Estrelas',
                'contato' => $contatos[array_rand($contatos)],
                'valor' => $totalMensal,
                'valor_aprovado' => $valorAprovado,
                'data_aprovacao' => $dataAprovacao,
                'da_cotacao' => true,
                'total_mensal' => $totalMensal,
                'total_anual' => round($totalMensal * 12, 2),
                'qtd_postos' => $qtdPostos,
                'qtd_funcionarios' => $qtdFunc,
                'va_total' => $vaTotal,
                'postos' => $postos,
                'identificacao' => [
                    'numProposta' => $numero,
                    'data' => $data->toDateString(),
                    'cliente' => $cliente,
                    'empresa' => $empresa,
                    'cct' => $cct,
                    'periodicidade' => $periodicidade,
                    'modelo' => $modelo,
                ],
                'created_by' => $author?->id,
                'created_at' => $data,
                'updated_at' => $data,
            ]);
        }

        $this->command->info("  → {$total} propostas criadas.");
    }

    private function downloadImage(string $url, string $destPath): ?string
    {
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 15, 'follow_location' => 1],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $contents = @file_get_contents($url, false, $context);
            if ($contents === false || strlen($contents) < 1000) {
                $this->command->warn("  ! Falha ao baixar: {$url}");
                return null;
            }
            Storage::disk('public')->put($destPath, $contents);
            return $destPath;
        } catch (\Throwable $e) {
            $this->command->warn("  ! Erro ao baixar {$url}: " . $e->getMessage());
            return null;
        }
    }
}
