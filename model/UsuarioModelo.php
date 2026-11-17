<?php
// Modelo: las cuentas de usuario
class UsuarioModelo
{
    public function __construct(private PDO $pdo) {}

    // Un usuario activo por su nombre de usuario; null si no existe
    public function buscarActivo(string $usuario): ?array
    {
        $consulta = $this->pdo->prepare(
            "SELECT id, usuario, nombre, contrasena, rol FROM usuarios WHERE usuario = ? AND activo = 1"
        );
        $consulta->execute([$usuario]);
        return $consulta->fetch() ?: null;
    }

    public function registrarAcceso(int $id): void
    {
        $this->pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$id]);
    }

    // Si el hash se hizo con un algoritmo o coste antiguo, se guarda uno nuevo
    public function actualizarHash(int $id, string $hash): void
    {
        $this->pdo->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?")->execute([$hash, $id]);
    }
}
