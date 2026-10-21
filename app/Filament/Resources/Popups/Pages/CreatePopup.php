<?php

namespace App\Filament\Resources\Popups\Pages;

use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Concerns\HasAiProposalPrefill;
use App\Filament\Resources\Popups\PopupResource;
use App\Models\AiChatProposal;
use Filament\Resources\Pages\CreateRecord;

class CreatePopup extends CreateRecord
{
    use HasAiFormFill;
    use HasAiProposalPrefill;

    protected static string $resource = PopupResource::class;

    // AI 비서 카드(?ai_proposal=)로 열렸을 때 읽을 제안 종류.
    protected static string $aiProposalKind = AiChatProposal::KIND_POPUP;

    protected function afterCreate(): void
    {
        $this->markAiProposalUsed();
    }
}
