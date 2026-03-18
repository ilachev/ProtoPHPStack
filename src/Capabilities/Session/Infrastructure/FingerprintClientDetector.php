<?php

declare(strict_types=1);

namespace App\Capabilities\Session\Infrastructure;

use App\Capabilities\Session\Application\ClientConfig;
use App\Capabilities\Session\Application\ClientDetector;
use App\Capabilities\Session\Application\ClientIdentity;
use App\Capabilities\Session\Application\ClientSimilarity;
use App\Capabilities\Session\Application\SessionClientPayload;
use App\Capabilities\Session\Domain\Session;
use App\Capabilities\Session\Domain\SessionRepository;
use Psr\Http\Message\ServerRequestInterface;

final readonly class FingerprintClientDetector implements ClientDetector
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private ClientConfig $config,
    ) {}

    public function findSimilarClients(ServerRequestInterface $request, bool $includeCurrent = false): array
    {
        /** @var ?Session $currentSession */
        $currentSession = $request->getAttribute('session');

        if ($currentSession === null) {
            return [];
        }

        $currentIdentity = ClientIdentity::fromSession($currentSession);
        $allSessions = $this->sessionRepository->findAll();

        $otherSessions = $includeCurrent
            ? $allSessions
            : array_filter(
                $allSessions,
                static fn(Session $session) => $session->id !== $currentSession->id,
            );

        if (empty($otherSessions)) {
            return [];
        }

        $similarities = [];
        foreach ($otherSessions as $session) {
            $otherIdentity = ClientIdentity::fromSession($session);
            $score = $this->calculateSimilarityScore($currentIdentity, $otherIdentity);

            if ($score >= $this->config->similarityThreshold) {
                $similarities[] = new ClientSimilarity($otherIdentity, $score);
            }
        }

        usort($similarities, static fn(ClientSimilarity $a, ClientSimilarity $b) => $b->score <=> $a->score);

        return array_map(
            static fn(ClientSimilarity $item): ClientIdentity => $item->identity,
            $similarities,
        );
    }

    public function isRequestSuspicious(ServerRequestInterface $request): bool
    {
        /** @var ?Session $currentSession */
        $currentSession = $request->getAttribute('session');

        if ($currentSession === null) {
            return false;
        }

        $currentIdentity = ClientIdentity::fromSession($currentSession);
        $currentIp = $currentIdentity->ipAddress;

        if ($currentIp === 'unknown') {
            return false;
        }

        $allSessions = $this->sessionRepository->findAll();
        $sessionsWithSameIp = array_filter(
            $allSessions,
            static function (Session $session) use ($currentIp, $currentSession) {
                if ($session->id === $currentSession->id) {
                    return false;
                }

                return SessionClientPayload::fromSession($session)->ipAddress === $currentIp;
            },
        );

        return \count($sessionsWithSameIp) >= $this->config->maxSessionsPerIp;
    }

    private function calculateSimilarityScore(ClientIdentity $identity1, ClientIdentity $identity2): float
    {
        if ($identity1->id === $identity2->id) {
            return 1.0;
        }

        $score = 0.0;

        if ($identity1->ipAddress !== 'unknown' && $identity1->ipAddress === $identity2->ipAddress) {
            $score += $this->config->ipMatchWeight;
        }

        if ($identity1->userAgent !== null && $identity1->userAgent === $identity2->userAgent) {
            $score += $this->config->userAgentMatchWeight;
        }

        if (!empty($identity1->attributes) || !empty($identity2->attributes)) {
            $allAttributes = array_unique(array_merge(
                array_keys($identity1->attributes),
                array_keys($identity2->attributes),
            ));

            if (!empty($allAttributes)) {
                $matchCount = 0;

                foreach ($allAttributes as $key) {
                    if (
                        isset($identity1->attributes[$key], $identity2->attributes[$key])
                        && $identity1->attributes[$key] === $identity2->attributes[$key]
                    ) {
                        ++$matchCount;
                    }
                }

                $attributeMatchPercent = $matchCount / \count($allAttributes);
                $score += $this->config->attributesMatchWeight * $attributeMatchPercent;
            }
        }

        if (
            $identity1->ipAddress !== 'unknown'
            && $identity1->ipAddress === $identity2->ipAddress
            && $identity1->userAgent !== null
            && $identity1->userAgent === $identity2->userAgent
        ) {
            $score = max($score, 0.9);
        }

        return $score;
    }
}
