<?php
// /app/Services/RedisService.php
namespace App\Services;

class RedisService {
    private \Redis $redis;

    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function getClient(): \Redis {
        return $this->redis;
    }

    public function set(string $key, $value, int $ttl = 0): bool {
        return $ttl > 0
            ? $this->redis->setex($key, $ttl, $value)
            : $this->redis->set($key, $value);
    }

    public function get(string $key) {
        return $this->redis->get($key);
    }

    public function del(string $key): int {
        return $this->redis->del([$key]);
    }

    // --- FILA FIFO ---
    public function enqueue(string $key, $value): int {
        // adiciona no final da lista (FIFO)
        return $this->redis->rPush($key, $value);
    }

    public function dequeue(string $key, int $timeout = 0) {
        // remove do início da lista, bloqueando se estiver vazia
        $result = $this->redis->blPop([$key], $timeout);
        return $result ? $result[1] : null; // retorna apenas o valor
    }

    public function lLen(string $key): int {
        return $this->redis->lLen($key);
    }

    public function lRange(string $key, int $start, int $end): array {
        return $this->redis->lRange($key, $start, $end);
    }

    /**
   * Verifica se a chave existe no Redis
   */
  public function exists(string $key): bool
  {
      return $this->redis->exists($key) > 0;
  }

  /**
   * Retorna o TTL restante da chave
   * -2 = não existe
   * -1 = existe, mas sem TTL
   */
  public function ttl(string $key): int
  {
      return $this->redis->ttl($key);
  }

  /**
     * Retorna todos os campos e valores de uma Hash do Redis.
     * Útil para ler o estado completo de uma chave como 'loc:user:{userId}'.
     */
    public function hGetAll(string $key): array
    {
        // O método hGetAll retorna um array associativo dos campos e seus valores.
        return $this->redis->hGetAll($key);
    }

    /**
     * Incrementa o valor de uma chave (usado para o contador de notificações).
     */
    public function incr(string $key): int {
        return $this->redis->incr($key);
    }

    /**
     * Define o tempo de expiração de uma chave em segundos.
     */
    public function expire(string $key, int $ttl): bool {
        return $this->redis->expire($key, $ttl);
    }

    /**
     * Adiciona um valor ao final da lista (usado pelo controller como rPush).
     * Nota: Você já tem o 'enqueue', mas o controller chama 'rPush' explicitamente.
     */
    public function rPush(string $key, $value): int {
        return $this->redis->rPush($key, $value);
    }
    /**
     * Remove e retorna o último elemento da lista (não bloqueante).
     */
    public function rPop(string $key) {
        return $this->redis->rPop($key);
    }

    /**
     * Adiciona um valor a um conjunto (Set). Útil para lista única de IDs.
     */
    public function sAdd(string $key, $value): int {
        return $this->redis->sAdd($key, $value);
    }

    /**
     * Remove um valor do conjunto.
     */
    public function sRem(string $key, $value): int {
        return $this->redis->sRem($key, $value);
    }

    /**
     * Retorna todos os membros do conjunto.
     */
    public function sMembers(string $key): array {
        return $this->redis->sMembers($key);
    }
}
