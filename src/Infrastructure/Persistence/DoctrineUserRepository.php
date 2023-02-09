<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\ConfirmationToken;
use App\Domain\Status;
use App\Domain\User;
use App\Domain\UserId;
use App\Domain\UserRepository;
use App\SharedKernel\Exception\NotFoundException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private readonly Connection $connection){}

    /**
     * @throws Exception
     */
    public function store(User $user): void
    {
        $this->connection
            ->createQueryBuilder()
            ->insert('users')
            ->values([
                'id' => ':id',
                'email' => ':email',
                'password' => ':password',
                'first_name' => ':firstName',
                'last_name' => ':lastName',
                'status' => ':status',
            ])
            ->setParameters([
                'id' => $user->getId()->toString(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'status' => $user->getStatus()->value,
            ])
            ->executeStatement();

        $this->connection
            ->createQueryBuilder()
            ->insert('users_confirmation')
            ->values([
                'users_id' => ':userId',
                'email' => ':email',
                'confirmation_token' => ':confirmationToken',
            ])
            ->setParameters([
                'userId' => $user->getId()->toString(),
                'email' => $user->getEmail(),
                'confirmationToken' => $user->getConfirmationToken()->token,
            ])
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function save(User $user): void
    {
        $this->connection
            ->createQueryBuilder()
            ->update('users')
            ->set('first_name', ':firstName')
            ->set('last_name', ':lastName')
            ->set('status', ':status')
            ->set('updated_at', ':updatedAt')
            ->where('id = :id')
            ->setParameters([
                'id' => $user->getId()->toString(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'status' => $user->getStatus()->value,
                'updatedAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function existsByEmail(string $email): bool
    {
        return $this->connection
            ->createQueryBuilder()
            ->select('1')
            ->from('users')
            ->where('email = :email')
            ->setParameters([
                'email' => $email
            ])
            ->executeQuery()
            ->rowCount() > 0;
    }

    /**
     * @throws Exception
     * @throws NotFoundException
     */
    public function getByConfirmationToken(ConfirmationToken $token): User
    {
        $row = $this->connection
            ->createQueryBuilder()
            ->select(
                'u.id',
                'u.email',
                'u.password',
                'u.first_name',
                'u.last_name',
                'u.status',
                'uc.confirmation_token',
            )
            ->from('users_confirmation', 'uc')
            ->join('uc', 'users', 'u', 'uc.users_id = u.id')
            ->where('uc.confirmation_token = :token')
            ->setParameters([
                'token' => $token->token,
            ])
            ->executeQuery()
            ->fetchAssociative();

        if (empty($row)) {
            throw new NotFoundException('User with given confirmation token not found');
        }

        return new User(
            UserId::fromString($row['id']),
            $row['email'],
            $row['password'],
            $row['first_name'],
            $row['last_name'],
            Status::from($row['status']),
            new ConfirmationToken($row['confirmation_token']),
        );
    }
}
