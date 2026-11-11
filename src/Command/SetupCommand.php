<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Desk;
use App\Entity\Office;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:setup', description: 'Create a demo office and local accounts in an empty database')]
final class SetupCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->em->getRepository(User::class)->count([]) > 0) {
            $io->note('Accounts already exist. No data was changed.');

            return Command::SUCCESS;
        }
        $credentials = [];
        $this->em->wrapInTransaction(function () use (&$credentials) {
            foreach ([['admin@hotdesk.test', 'Bartosz Misiek', 'ROLE_ADMIN'], ['anna@hotdesk.test', 'Anna Kowalska', 'ROLE_USER'], ['piotr@hotdesk.test', 'Piotr Nowak', 'ROLE_USER']] as [$email, $name, $role]) {
                $user = new User();
                $user->email = $email;
                $user->name = $name;
                $user->role = $role;
                $password = bin2hex(random_bytes(9));
                $user->password = $this->hasher->hashPassword($user, $password);
                $this->em->persist($user);
                $credentials[] = ['email' => $email, 'password' => $password, 'role' => $role];
            }
            $office = new Office();
            $office->name = 'Kraków · Zabłocie';
            $office->address = 'ul. Przemysłowa 12, Kraków';
            $this->em->persist($office);
            for ($row = 0; $row < 3; ++$row) {
                for ($column = 0; $column < 4; ++$column) {
                    $desk = new Desk();
                    $desk->office = $office;
                    $desk->label = chr(65 + $row).sprintf('%02d', $column + 1);
                    $desk->equipment = 0 === $column % 2 ? 'Monitor · USB-C · Adjustable desk' : 'Monitor · USB-C';
                    $desk->x = $column;
                    $desk->y = $row;
                    $this->em->persist($desk);
                }
            }
        });
        $directory = $this->projectDir.'/.local';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/accounts.json', json_encode($credentials, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
        chmod($directory.'/accounts.json', 0600);
        $io->success('Demo office created. Generated passwords are in .local/accounts.json.');

        return Command::SUCCESS;
    }
}
