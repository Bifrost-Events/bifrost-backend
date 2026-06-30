<?php

declare(strict_types=1);

namespace App\Service\UseCases;

use App\Contracts\Repositories\ParticipantRepositoryPort;
use App\Support\JaktfeltId;

/** Sørger for at en bruker har minst én deltaker (eier) når ingen match finnes. */
final class EnsureParticipantForUserUseCase
{
    public function __construct(private readonly ParticipantRepositoryPort $participantRepo)
    {
    }

    /** @return array<string, mixed>|null */
    public function execute(
        int $authUserId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
        ?\DateTimeInterface $dateOfBirth = null,
    ): ?array {
        if ($authUserId <= 0) {
            return null;
        }

        $first = $firstName !== null && trim($firstName) !== '' ? trim($firstName) : 'Bruker';
        $last = $lastName !== null && trim($lastName) !== '' ? trim($lastName) : (string) $authUserId;
        $phoneTrim = $phone !== null && trim($phone) !== '' ? trim($phone) : null;

        if ($this->participantRepo->findByNamesAndPhone($first, $last, $phoneTrim) !== null) {
            return null;
        }
        if ($this->participantRepo->findByNamesAndPhone($first, $last, null) !== null) {
            return null;
        }

        $apenVoksen = $this->participantRepo->findClassByCode('apen_voksen');
        $classId = $apenVoksen !== null ? (int) $apenVoksen['id'] : 2;

        $participant = $this->participantRepo->createForUser(
            $authUserId,
            $first,
            $last,
            $classId,
            $dateOfBirth,
            $phoneTrim,
            null,
        );

        $jaktfeltId = $this->generateUniqueJaktfeltId();
        $this->participantRepo->addJaktfeltId((int) $participant['id'], $jaktfeltId->value);
        $participant['jaktfelt_id'] = $jaktfeltId->value;

        return $participant;
    }

    private function generateUniqueJaktfeltId(): JaktfeltId
    {
        for ($i = 0; $i < 100; $i++) {
            $id = JaktfeltId::generateRandom();
            if (!$this->participantRepo->jaktfeltIdExists($id->value)) {
                return $id;
            }
        }

        throw new \RuntimeException('Kunne ikke generere unik Jaktfelt-ID');
    }
}
