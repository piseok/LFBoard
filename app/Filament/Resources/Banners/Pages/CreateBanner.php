<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Concerns\HasAiProposalPrefill;
use App\Filament\Resources\Banners\BannerResource;
use App\Models\AiChatProposal;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    use HasAiFormFill;
    use HasAiProposalPrefill;

    protected static string $resource = BannerResource::class;

    // AI 비서 카드(?ai_proposal=)로 열렸을 때 읽을 제안 종류.
    protected static string $aiProposalKind = AiChatProposal::KIND_BANNER;

    protected function afterCreate(): void
    {
        $this->markAiProposalUsed();
    }
}
