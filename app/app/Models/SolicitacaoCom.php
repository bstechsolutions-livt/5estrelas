<?php

namespace App\Models;

use App\Http\Controllers\SolicitacoesController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SolicitacaoCom extends Model
{
    use SoftDeletes;

    protected $table = 'intranet_solicitacao_com';

    protected $fillable = [
        'solicitacao_id',
        'usuario',
        'comentario',
        'private'
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Funcionario::class, 'usuario')
            ->select(['matricula', 'nome', 'email', 'areaatuacao', 'fone'])
            ->withDefault();
    }

    // Accessor para foto_perfil do usuário do comentário
    public function getFotoPerfilAttribute()
    {
        $intranetUsuario = DB::table('intranet_usuario')
            ->leftJoin('intranet_files', 'intranet_usuario.foto_perfil_id', '=', 'intranet_files.id')
            ->where('intranet_usuario.matricula', $this->attributes['usuario'])
            ->select('intranet_files.external_link as foto')
            ->first();

        return $intranetUsuario?->foto;
    }

    protected $appends = ['foto_perfil'];

    /**
     * Prefixos de comentários gerados automaticamente pelo sistema.
     * Comentários que iniciam com esses textos não podem ser excluídos.
     */
    public const PREFIXOS_SISTEMA = [
        'Atendimento finalizado',
        'Atendimento foi finalizado',
        'Resolução recusada,',
        'Solicitação cancelada,',
        'Retorno ao Solicitante.',
        'Devolução para',
        'Departamento alterado,',
        'Solicitante alterado.',
        'Agendamento foi finalizado',
    ];

    /**
     * Verifica se o comentário foi gerado automaticamente pelo sistema.
     */
    public function isSistema(): bool
    {
        $texto = strip_tags(trim($this->comentario ?? ''));
        foreach (self::PREFIXOS_SISTEMA as $prefixo) {
            if (str_starts_with($texto, $prefixo)) {
                return true;
            }
        }
        return false;
    }

    public function arquivos()
    {
        return $this->hasMany(SolicitacaoComArq::class, 'comentario_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($comentario) {
            // Pegando a solicitação relacionada
            $solicitacao = Solicitacao::find($comentario->solicitacao_id);

            $matriculaFinalizador = session('auth')?->matricula ?? 99999999;

            $usuariosDest = SolicitacaoCom::where('solicitacao_id', $solicitacao->id)
                ->where('usuario', '!=', $matriculaFinalizador)
                ->pluck('usuario')
                ->toArray();

            if ($solicitacao->usuario_solicitante != $matriculaFinalizador) {
                $usuariosDest[] = $solicitacao->usuario_solicitante;
            }

            $usuariosDest = array_values(array_unique($usuariosDest)); // Remove duplicatas e reorganiza os índices
            $titulo = 'Novo comentário na solicitação ' . $solicitacao->id;
            $mensagem = strlen($comentario->comentario) > 60
                ? substr($comentario->comentario, 0, 57) . "..."
                : $comentario->comentario;
            $origem = 'solicitacoes.comentarios';
            $link = url('/solicitacoes/lista?solicitacao=' . $solicitacao->id);

            $solController = new SolicitacoesController();
            $solController->criaNotificacao($titulo, $mensagem, $usuariosDest, $origem, $link);
        });
    }
}
