<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiText;
use Carbon\CarbonInterface;

// 요청마다 서버가 만드는 시스템 지시문 - 모델에게 "이 CMS 의 관리자 화면"과 "오늘이 언제인지"를 알려 준다.
// 이전에는 시스템 지시문이 없어 모델이 우리 관리자 화면을 몰랐고 오늘 날짜도 몰랐다.
//
//  - 화면 지도는 현재 관리자가 접근할 수 있는 화면만 담는다(권한 없는 화면은 존재 자체를 알리지 않는다).
//  - URL 은 넣지 않는다 - 관리자 경로(config app.admin_path)는 외부 제공자로 나가면 안 되는 값이다(모델에는 키만 준다).
//  - 개인정보(관리자 이름·이메일 등)는 넣지 않는다.
class AiSystemPromptBuilder
{
    private const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];

    public function __construct(private readonly AiProposalService $proposals) {}

    public function build(User $admin, ?string $screenPath = null, bool $toolsEnabled = true, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        $lines = [];

        $lines[] = '당신은 웹사이트 관리자 화면(CMS)에서 일하는 AI 비서입니다. 관리자가 사이트 콘텐츠(게시글·팝업·배너·페이지 등)를 만들고 관리하는 일을 돕습니다.';
        $lines[] = '';
        $lines[] = '[현재 시각] '.$now->format('Y-m-d').'('.self::WEEKDAYS[$now->dayOfWeek].') '.$now->format('H:i').', 시간대 '.$now->getTimezone()->getName().' - 날짜 계산(내일·다음 주·2주간 등)은 이 시각을 기준으로 합니다.';
        $lines[] = '';
        $lines[] = '[규칙]';
        $lines[] = '- 한국어로 짧고 구체적으로 답합니다(관리자가 다른 언어로 물으면 그 언어로).';
        $lines[] = '- 이 CMS 에 없는 기능이나 화면을 지어내지 않습니다. 아래 "관리자 화면 지도"에 없는 것은 모른다고 말하고 할 수 있는 다른 방법을 안내합니다.';

        if ($toolsEnabled) {
            $lines[] = '- 관리자가 팝업·게시글·페이지·배너를 만들어 달라고 하면 해당 propose_* 도구로 초안을 제안합니다. 도구는 아무것도 저장하지 않습니다 - 관리자가 버튼으로 작성 화면을 열어 직접 저장해야 합니다. 이 점을 답변에 한 문장으로 알려 줍니다.';
            $lines[] = '- 어느 화면에서 하는지 묻거나 화면을 열어 달라고 하면 open_admin_page 도구로 이동 버튼을 제안합니다(자동으로 이동하지 않습니다).';
            $lines[] = '- 도구를 호출할 때도 관리자에게 보여 줄 짧은 안내 문장을 함께 씁니다. 한 번에 도구는 한두 개만 씁니다.';
        } else {
            $lines[] = '- 지금은 도구를 쓸 수 없습니다. 화면 이름과 메뉴 위치를 글로 안내하고, 초안이 필요하면 본문을 답변에 직접 씁니다.';
        }

        $lines[] = '- 비밀번호·주민등록번호·카드번호 같은 개인정보는 요청하지 않고, 대화에 나와도 반복하지 않습니다(이 대화 내용은 외부 AI 제공자에게 전송됩니다).';
        $lines[] = '- 확실하지 않은 내용(법적 문구·가격 등)은 단정하지 말고 관리자가 확인해야 한다고 알려 줍니다.';

        $screens = AdminScreenMap::accessibleFor($admin);

        $lines[] = '';
        $lines[] = '[관리자 화면 지도] (이 관리자가 열 수 있는 화면만 - 키: 이름 - 할 수 있는 일)';

        if ($screens === []) {
            $lines[] = '- (열 수 있는 화면이 없습니다)';
        }

        foreach ($screens as $key => $screen) {
            $lines[] = "- {$key}: {$screen['label']} - {$screen['summary']}";
        }

        $context = AiFormFillSchema::detect($screenPath);

        if ($context !== null) {
            $fields = implode(', ', array_map(fn (array $field) => $field['label'], $context['fields']));
            $lines[] = '';
            $lines[] = "[지금 열려 있는 화면] 관리자는 지금 \"{$context['label']}\" 화면을 열어 두었습니다. 이 화면에서 답변을 채울 수 있는 칸: {$fields}. 관리자가 \"이 화면에 채울 글 써줘\"라고 하면 도구 없이 칸에 넣을 본문을 답변으로 씁니다(관리자가 답변 아래 \"채우기\" 버튼을 누릅니다).";
        }

        if ($toolsEnabled && AdminScreenMap::canCreate($admin, 'posts')) {
            $boards = $this->proposals->writableBoards($admin)
                ->map(fn ($board) => AiText::singleLine($board->name, 50))
                ->filter()
                ->values()
                ->all();

            if ($boards !== []) {
                $lines[] = '';
                $lines[] = '[글을 올릴 수 있는 게시판] '.implode(', ', $boards);
            }
        }

        return implode("\n", $lines);
    }
}
