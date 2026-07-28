<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB 스키마 자체에 설명(COMMENT)이 하나도 없어서, phpMyAdmin 등 DB 툴에서 스키마만 보고는 각
// 테이블/컬럼이 뭘 뜻하는지 알 수 없었다는 요청으로 전체 테이블/컬럼에 COMMENT를 채운다.
// dev/future-mobility 두 브랜치가 테이블 구성이 다르므로(LMS 관련 테이블은 future-mobility에만
// 존재), 이 파일은 두 브랜치에 동일하게 커밋하되 존재하지 않는 테이블/컬럼은 조용히 건너뛴다.
// 컬럼 COMMENT는 타입/NULL 여부/기본값을 실행 시점에 information_schema에서 직접 읽어와 그대로
// 재사용하므로(MODIFY COLUMN이 정의 전체를 다시 요구하는 MySQL 제약 때문), 여기 적힌 타입 정보와
// 실제 컬럼이 달라도(마이그레이션 순서 등) 항상 안전하게 COMMENT만 덧붙는다.
return new class extends Migration
{
    private const TABLE_COMMENTS = [
        'admin_audit_logs' => '관리자 활동 감사로그 — 생성/수정/삭제 등 관리자 조작 이력',
        'ai_chat_conversations' => 'AI 챗봇 대화방(회원별)',
        'ai_chat_messages' => 'AI 챗봇 대화 메시지',
        'assignment_submissions' => '과제 제출물',
        'assignments' => '과제(챕터별 또는 과정 최종과제)',
        'banned_words' => '금칙어',
        'banners' => '배너(메인/푸터 등 위치별)',
        'board_categories' => '게시판별 카테고리',
        'boards' => '게시판 설정(스킨/권한/옵션)',
        'certificates' => '수료증 발급 내역',
        'chapter_unlocks' => '회원별 챕터 잠금 해제 기록',
        'chapters' => '교육과정 챕터(강의 단위)',
        'comments' => '게시글 댓글',
        'course_categories' => '교육과정 카테고리(최대 2단계 트리)',
        'course_wishlists' => '회원 관심교육 등록',
        'courses' => '교육과정(온라인/오프라인)',
        'email_templates' => '발송 이메일 템플릿',
        'enrollments' => '수강신청(결제/학습 상태 포함)',
        'inquiries' => '1:1 문의',
        'ip_country_ranges' => 'IP 대역 → 국가코드 매핑(해외 로그인 탐지용)',
        'languages' => '다국어(로케일) 설정',
        'maintenance_reports' => '유지보수 업체 작업 보고서',
        'marketing_mail_logs' => '마케팅 메일 발송 로그',
        'media_files' => '미디어 라이브러리(이미지 보관/재사용)',
        'menus' => '메뉴(헤더/사이트맵, 최대 3단계 트리)',
        'pages' => '정적 페이지(회사소개 등)',
        'payments' => '결제 내역(카드/가상계좌)',
        'policies' => '약관/정책(이용약관, 개인정보처리방침 등)',
        'policy_consents' => '회원별 약관 동의 이력',
        'popups' => '레이어 팝업',
        'post_files' => '게시글 첨부파일',
        'posts' => '게시글',
        'site_settings' => '사이트 설정(key-value)',
        'social_accounts' => '소셜 로그인 연동 계정',
        'user_login_countries' => '회원별 로그인 국가 이력',
        'users' => '회원 및 관리자 계정',
        'vendor_notices' => '유지보수 업체 공지사항(외부 동기화)',
        'video_progresses' => '회원별 영상 시청 진도',
        'videos' => '챕터별 강의 영상(Vimeo)',
        'visit_logs' => '방문 로그(일별/IP/경로)',
    ];

    private const COLUMN_COMMENTS = [
        'admin_audit_logs' => [
            'id' => '기본 키',
            'admin_user_id' => '행위자 관리자 user_id(탈퇴 시 null)',
            'admin_name' => '행위 당시 관리자 이름 스냅샷(탈퇴 후에도 누가 했는지 남기기 위함)',
            'action' => '수행한 동작(created/updated/deleted 등)',
            'auditable_type' => '대상 모델 클래스명(다형 관계)',
            'auditable_id' => '대상 레코드 ID',
            'auditable_label' => '대상을 사람이 알아볼 수 있는 라벨(제목 등)',
            'ip' => '요청 IP',
            'url' => '요청 URL',
            'before' => '변경 전 값(JSON)',
            'changes' => '변경 후 값(JSON)',
            'created_at' => '기록 일시',
        ],
        'ai_chat_conversations' => [
            'id' => '기본 키',
            'user_id' => '대화 소유 회원',
            'provider' => 'AI 제공자(openai/gemini)',
            'title' => '대화방 제목',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제일시',
        ],
        'ai_chat_messages' => [
            'id' => '기본 키',
            'conversation_id' => '소속 대화방',
            'role' => '발화자(user/assistant)',
            'content' => '메시지 본문',
            'image_path' => '첨부 이미지 경로(있는 경우)',
            'created_at' => '전송일시',
        ],
        'assignment_submissions' => [
            'id' => '기본 키',
            'assignment_id' => '제출 대상 과제',
            'user_id' => '제출한 회원',
            'file_path' => '제출 파일 경로',
            'original_name' => '제출 파일 원본 파일명',
            'submitted_at' => '제출일시',
            'status' => '채점 상태(제출완료/채점중 등)',
            'is_passed' => '합격 여부(null=미채점)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시(재제출 시 갱신)',
        ],
        'assignments' => [
            'id' => '기본 키',
            'chapter_id' => '소속 챕터(챕터별 과제인 경우, null 가능)',
            'course_id' => '소속 과정(과정 최종과제인 경우, null 가능)',
            'title' => '과제 제목',
            'description' => '과제 설명',
            'file_path' => '강사가 첨부한 안내 파일 경로',
            'file_original_name' => '강사 첨부 파일 원본 파일명',
            'requires_grading_to_unlock_next' => '합격 채점이 나야 다음 챕터가 열리는지 여부',
            'info_blocks' => '추가 안내 블록(JSON)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'banned_words' => [
            'id' => '기본 키',
            'word' => '금칙어',
            'type' => '적용 범위(all 등)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'banners' => [
            'id' => '기본 키',
            'group_key' => '배너 노출 위치 그룹(메인 상단/푸터 등)',
            'content_type' => '콘텐츠 유형(image/html)',
            'locale' => '언어(로케일)',
            'title' => '배너 제목(관리용/대체텍스트 참고용)',
            'image_path' => '배너 이미지 경로',
            'html_content' => 'HTML 직접 입력 콘텐츠(content_type=html일 때)',
            'link_url' => '클릭 시 이동할 링크',
            'link_target' => '링크 열림 방식(_blank/_self)',
            'alt_text' => '이미지 대체텍스트',
            'captions' => '슬라이드 캡션 등 부가 정보(JSON)',
            'started_at' => '노출 시작일시',
            'ended_at' => '노출 종료일시',
            'click_count' => '클릭 수',
            'is_active' => '노출 활성화 여부',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'board_categories' => [
            'id' => '기본 키',
            'board_id' => '소속 게시판',
            'name' => '카테고리명',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'boards' => [
            'id' => '기본 키',
            'name' => '게시판명',
            'slug' => 'URL 식별자',
            'locale' => '언어(로케일)',
            'skin' => '목록 화면 스킨(list/gallery/faq/custom-fields 등)',
            'layout' => '레이아웃 방식',
            'use_editor' => '에디터 사용 여부',
            'allow_comment' => '댓글 허용 여부',
            'allow_reply' => '답글 허용 여부',
            'allow_file' => '첨부파일 허용 여부',
            'allow_anonymous' => '비회원 글쓰기 허용 여부',
            'allow_image_upload' => '본문 이미지 업로드 허용 여부',
            'use_captcha' => '캡차 사용 여부',
            'requires_identity_verification' => '본인인증 필요 여부',
            'identity_verification_consent_text' => '본인인증 동의 안내 문구',
            'min_read_level' => '읽기 최소 회원등급',
            'min_write_level' => '쓰기 최소 회원등급',
            'min_comment_level' => '댓글 최소 회원등급',
            'files_per_post' => '글당 첨부파일 최대 개수',
            'per_page' => '페이지당 게시글 수',
            'order_by' => '정렬 기준(latest/views 등)',
            'description' => '게시판 설명',
            'is_active' => '활성화 여부',
            'exclude_from_search' => '통합검색 제외 여부',
            'custom_field_schema' => '게시판별 커스텀 필드 정의(JSON)',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'certificates' => [
            'id' => '기본 키',
            'enrollment_id' => '발급 대상 수강신청',
            'certificate_number' => '수료증 일련번호(연도별 순번, 예: 2026-001)',
            'issued_at' => '발급일시',
            'pdf_path' => '생성된 PDF 파일 경로(생성 실패 시 null)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'chapter_unlocks' => [
            'id' => '기본 키',
            'user_id' => '회원',
            'chapter_id' => '잠금 해제된 챕터',
            'unlocked_at' => '잠금 해제일시',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'chapters' => [
            'id' => '기본 키',
            'course_id' => '소속 교육과정',
            'title' => '챕터명',
            'sort_order' => '정렬 순서(수강 순서)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'comments' => [
            'id' => '기본 키',
            'post_id' => '소속 게시글',
            'user_id' => '작성 회원(비회원 작성 시 null)',
            'parent_id' => '부모 댓글(대댓글인 경우)',
            'depth' => '댓글 깊이(0=댓글, 1=대댓글)',
            'content' => '댓글 내용',
            'author_name' => '비회원 작성자명',
            'author_password' => '비회원 작성 비밀번호(해시)',
            'ip' => '작성 IP',
            'is_active' => '노출 여부(삭제 시 false)',
            'created_at' => '작성일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제일시',
        ],
        'course_categories' => [
            'id' => '기본 키',
            'parent_id' => '상위 카테고리(최상위면 null)',
            'name' => '카테고리명',
            'sort_order' => '정렬 순서',
            'certificate_template' => '이 카테고리 소속 과정의 수료증 양식 키',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'course_wishlists' => [
            'id' => '기본 키',
            'user_id' => '회원',
            'course_id' => '관심 등록한 교육과정',
            'created_at' => '등록일시',
            'updated_at' => '수정일시',
        ],
        'courses' => [
            'id' => '기본 키',
            'category_id' => '소속 카테고리',
            'type' => '과정 유형(online/offline)',
            'title' => '과정명',
            'thumbnail' => '썸네일 이미지 경로',
            'price' => '수강료(원 단위, 0=무료)',
            'payment_test_mode' => '결제 테스트 모드 여부(실결제 없이 신청 처리)',
            'capacity' => '정원(null=제한없음)',
            'recruitment_start_at' => '접수 시작일시',
            'recruitment_end_at' => '접수 마감일시',
            'education_start_at' => '교육 시작일시',
            'education_end_at' => '교육 종료일시',
            'location' => '교육 장소(오프라인 과정)',
            'info_blocks' => '상세 안내 블록(JSON)',
            'daily_chapter_limit' => '일일 수강 가능 챕터 수 제한(null=제한없음)',
            'is_active' => '활성화 여부',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제일시',
        ],
        'email_templates' => [
            'id' => '기본 키',
            'type' => '템플릿 종류(발송 트리거)',
            'locale' => '언어(로케일)',
            'name' => '템플릿명',
            'subject' => '메일 제목',
            'body' => '메일 본문(HTML)',
            'is_active' => '활성화 여부',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'enrollments' => [
            'id' => '기본 키',
            'user_id' => '신청 회원',
            'course_id' => '신청 교육과정',
            'payment_status' => '결제 상태(결제완료/결제대기/관리자부여)',
            'learning_status' => '학습 상태(미학습/학습중/제출완료/채점중/수료/미수료)',
            'payment_id' => '마지막으로 연결된 결제 건(역참조, 결제 이력은 payments.enrollment_id가 정본)',
            'granted_by' => '관리자가 무료 부여한 경우 처리한 관리자 ID',
            'cancellation_requested_at' => '수강취소 신청일시',
            'cancelled_at' => '수강취소 확정일시',
            'created_at' => '신청일시',
            'updated_at' => '수정일시',
        ],
        'inquiries' => [
            'id' => '기본 키',
            'user_id' => '작성 회원(비회원 작성 시 null)',
            'type' => '문의 유형',
            'locale' => '언어(로케일)',
            'category' => '세부 카테고리',
            'name' => '작성자명',
            'email' => '연락 이메일',
            'phone' => '연락처',
            'author_password' => '비회원 작성 비밀번호(해시)',
            'title' => '문의 제목',
            'content' => '문의 내용',
            'file_path' => '첨부파일 경로',
            'status' => '처리 상태(pending 등)',
            'admin_reply' => '관리자 답변 내용',
            'replied_at' => '답변일시',
            'is_active' => '노출 여부',
            'created_at' => '작성일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제일시',
        ],
        'ip_country_ranges' => [
            'id' => '기본 키',
            'ip_start' => 'IP 대역 시작(정수 변환값)',
            'ip_end' => 'IP 대역 끝(정수 변환값)',
            'country_code' => '국가코드(ISO 2자리)',
        ],
        'languages' => [
            'id' => '기본 키',
            'code' => '언어코드(ko/en/ja 등)',
            'timezone' => '해당 언어 기본 표시 시간대',
            'name' => '언어명',
            'is_default' => '기본 언어 여부',
            'is_active' => '활성화 여부',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'maintenance_reports' => [
            'id' => '기본 키',
            'user_id' => '작성 관리자',
            'title' => '보고서 제목',
            'content' => '보고서 내용',
            'report_type' => '보고서 유형(notice 등)',
            'is_sent' => '발송(공지) 완료 여부',
            'sent_at' => '발송일시',
            'send_response' => '발송 결과 응답 로그',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'marketing_mail_logs' => [
            'id' => '기본 키',
            'subject' => '발송 메일 제목',
            'content' => '발송 메일 본문',
            'sent_count' => '발송 성공 건수',
            'failed_count' => '발송 실패 건수',
            'sent_by' => '발송한 관리자 ID',
            'sent_at' => '발송일시',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'media_files' => [
            'id' => '기본 키',
            'user_id' => '업로드한 관리자/회원',
            'original_name' => '원본 파일명',
            'stored_name' => '저장된 파일명',
            'file_path' => '저장 경로',
            'file_size' => '파일 크기(byte)',
            'mime_type' => 'MIME 타입',
            'alt_text' => '대체텍스트',
            'download_count' => '다운로드/사용 횟수',
            'created_at' => '업로드일시',
            'updated_at' => '수정일시',
        ],
        'menus' => [
            'id' => '기본 키',
            'parent_id' => '상위 메뉴(최상위면 null)',
            'title' => '메뉴명',
            'locale' => '언어(로케일)',
            'slug' => '식별용 슬러그',
            'type' => '연결 대상 유형(board/page/url/none)',
            'target_id' => '연결 대상 ID(게시판/페이지 등)',
            'url' => '직접 URL(type=url인 경우)',
            'target' => '링크 열림 방식(_self/_blank)',
            'min_level' => '노출 최소 회원등급',
            'access_mode' => '레벨 미달 시 처리 방식(hidden=숨김/locked=잠금표시)',
            'hidden_from_header' => '상단메뉴(GNB)에서만 숨김 여부(사이트맵에도 동일 적용)',
            'sort_order' => '정렬 순서',
            'is_active' => '활성화 여부',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'pages' => [
            'id' => '기본 키',
            'title' => '페이지 제목',
            'slug' => 'URL 식별자',
            'locale' => '언어(로케일)',
            'content_type' => '콘텐츠 작성 방식(editor/html_file)',
            'content' => '에디터로 작성한 본문(content_type=editor)',
            'html_file_path' => '업로드한 HTML 파일 경로(content_type=html_file)',
            'meta_title' => 'SEO 메타 타이틀',
            'meta_description' => 'SEO 메타 설명',
            'meta_keywords' => 'SEO 메타 키워드',
            'og_image' => 'SNS 공유용 OG 이미지',
            'min_level' => '열람 최소 회원등급',
            'is_active' => '활성화 여부',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제일시',
        ],
        'payments' => [
            'id' => '기본 키',
            'enrollment_id' => '연결된 수강신청(결제 시도별로 여러 건 가능)',
            'pg_provider' => 'PG사(inicis 등)',
            'pg_transaction_id' => 'PG 거래번호(TID)',
            'payment_method' => '결제수단(card/vbank)',
            'vbank_bank_name' => '가상계좌 은행명',
            'vbank_account_number' => '가상계좌 계좌번호',
            'vbank_holder_name' => '가상계좌 예금주',
            'vbank_due_at' => '가상계좌 입금기한',
            'amount' => '결제금액(원)',
            'status' => '결제 상태(완료/대기/취소)',
            'paid_at' => '결제(승인/입금) 완료일시',
            'cancelled_at' => '결제 취소일시',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'policies' => [
            'id' => '기본 키',
            'type' => '약관 종류(이용약관/개인정보처리방침 등)',
            'locale' => '언어(로케일)',
            'title' => '약관 제목',
            'content' => '현재 게시 중인 본문(content_type=editor)',
            'content_type' => '콘텐츠 작성 방식(editor/html_file)',
            'html_file_path' => '현재 게시 중인 HTML 파일 경로(content_type=html_file)',
            'is_required' => '가입/이용 시 필수 동의 여부',
            'is_active' => '활성화 여부',
            'version' => '현재 게시 중인 버전',
            'pending_version' => '예약된 다음 버전(사전고지 중인 개정판)',
            'pending_title' => '예약된 다음 버전 제목',
            'pending_content' => '예약된 다음 버전 본문',
            'pending_content_type' => '예약된 다음 버전 콘텐츠 작성 방식',
            'pending_html_file_path' => '예약된 다음 버전 HTML 파일 경로',
            'effective_at' => '예약된 다음 버전 시행일시(도래 시 자동 적용)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'policy_consents' => [
            'id' => '기본 키',
            'user_id' => '동의한 회원',
            'type' => '동의한 약관 종류',
            'locale' => '동의 시점 언어(로케일)',
            'version' => '동의한 약관 버전',
            'agreed_at' => '동의일시',
        ],
        'popups' => [
            'id' => '기본 키',
            'title' => '팝업 제목(관리용)',
            'locale' => '언어(로케일)',
            'content_type' => '콘텐츠 유형(image/html)',
            'image_path' => '팝업 이미지 경로',
            'html_content' => 'HTML 직접 입력 콘텐츠',
            'position' => '노출 위치(center 등)',
            'width' => '팝업 너비(px)',
            'height' => '팝업 높이(px)',
            'started_at' => '노출 시작일시',
            'ended_at' => '노출 종료일시',
            'is_active' => '활성화 여부',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'post_files' => [
            'id' => '기본 키',
            'post_id' => '소속 게시글',
            'original_name' => '원본 파일명',
            'stored_name' => '저장된 파일명',
            'file_path' => '저장 경로',
            'file_size' => '파일 크기(byte)',
            'mime_type' => 'MIME 타입',
            'sort_order' => '정렬 순서',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'posts' => [
            'id' => '기본 키',
            'board_id' => '소속 게시판',
            'user_id' => '작성 회원(비회원 작성 시 null)',
            'category_id' => '게시판 카테고리',
            'title' => '제목',
            'content' => '본문',
            'author_name' => '비회원 작성자명',
            'author_password' => '비회원 작성 비밀번호(해시)',
            'ip' => '작성 IP',
            'views' => '조회수',
            'is_global_notice' => '전체 게시판 공통 공지 여부',
            'is_notice' => '게시판 내 공지 여부',
            'is_secret' => '비밀글 여부',
            'is_active' => '노출 여부(삭제 시 false)',
            'is_draft' => '임시저장 여부',
            'recruitment_start_at' => '모집(접수) 시작일시',
            'recruitment_end_at' => '모집(접수) 마감일시',
            'custom_fields' => '게시판별 커스텀 필드 값(JSON)',
            'created_at' => '작성일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제일시',
        ],
        'site_settings' => [
            'id' => '기본 키',
            'key' => '설정 키',
            'value' => '설정 값',
            'group' => '설정 그룹(탭 구분)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'social_accounts' => [
            'id' => '기본 키',
            'user_id' => '연결된 회원',
            'provider' => '소셜 제공자(kakao/naver 등)',
            'provider_id' => '제공자측 고유 ID',
            'access_token' => '액세스 토큰',
            'refresh_token' => '리프레시 토큰',
            'created_at' => '연동일시',
            'updated_at' => '수정일시',
        ],
        'user_login_countries' => [
            'id' => '기본 키',
            'user_id' => '회원',
            'country_code' => '접속 국가코드(ISO 2자리)',
            'country_name' => '접속 국가명',
            'first_seen_at' => '해당 국가에서 처음 접속한 일시',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'users' => [
            'id' => '기본 키',
            'name' => '이름',
            'username' => '아이디(로그인용, 선택)',
            'email' => '이메일(로그인 ID로도 사용)',
            'locale' => '선호 언어(로케일)',
            'email_verified_at' => '이메일 인증일시',
            'password' => '비밀번호(해시)',
            'password_changed_at' => '비밀번호 마지막 변경일시',
            'level' => '회원 등급(0=게스트, 관리자는 admin_role로 구분)',
            'admin_role' => '관리자 역할(super 등, 일반회원은 null)',
            'admin_permissions' => '관리자 개별 권한 목록(JSON)',
            'vendor_notice_last_seen_id' => '마지막으로 확인한 유지보수 업체 공지 ID',
            'admin_locale_scope' => '관리자가 관리 가능한 언어 범위(JSON)',
            'admin_board_scope' => '관리자가 관리 가능한 게시판 범위(JSON)',
            'two_factor_secret' => '2단계 인증(TOTP) 비밀키(암호화)',
            'two_factor_recovery_codes' => '2단계 인증 복구코드(암호화, JSON)',
            'phone' => '연락처',
            'nickname' => '닉네임',
            'gender' => '성별',
            'birthdate' => '생년월일',
            'homepage' => '홈페이지 URL',
            'address' => '주소',
            'memo' => '관리자 메모',
            'is_active' => '활성화 여부(정지/탈퇴 시 false)',
            'marketing_agreed' => '마케팅 수신 동의 여부',
            'marketing_agreed_at' => '마케팅 수신 동의일시',
            'unsubscribe_token' => '메일 수신거부 링크용 토큰',
            'last_login_at' => '마지막 로그인일시(휴면 판정 기준)',
            'dormant_at' => '휴면 전환일시',
            'dormant_notice_sent_at' => '휴면 전환 사전 안내 발송일시',
            'withdrawal_notice_sent_at' => '휴면 후 탈퇴(익명화) 사전 안내 발송일시',
            'ci' => '본인확인기관 연계정보(CI, 암호화)',
            'di' => '본인확인기관 중복가입확인정보(DI, 암호화)',
            'phone_verified_at' => '휴대폰 본인인증 완료일시',
            'remember_token' => '로그인 유지(remember me) 토큰',
            'created_at' => '가입일시',
            'updated_at' => '수정일시',
            'deleted_at' => '소프트 삭제(탈퇴)일시',
        ],
        'vendor_notices' => [
            'id' => '기본 키',
            'external_id' => '유지보수 업체측 원본 공지 ID(중복 동기화 방지)',
            'title' => '공지 제목',
            'url' => '원본 공지 링크',
            'published_at' => '공지 게시일시',
            'created_at' => '동기화 수집일시',
            'updated_at' => '수정일시',
        ],
        'video_progresses' => [
            'id' => '기본 키',
            'user_id' => '회원',
            'video_id' => '시청한 영상',
            'watched_seconds' => '최대 시청 초(되감아도 감소하지 않음)',
            'completed_at' => '시청 완료(90% 이상) 처리일시',
            'last_watched_at' => '마지막 시청일시',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'videos' => [
            'id' => '기본 키',
            'chapter_id' => '소속 챕터',
            'title' => '영상 제목',
            'vimeo_video_id' => 'Vimeo 영상 ID',
            'duration_minutes' => '영상 길이(분)',
            'info_blocks' => '추가 안내 블록(JSON)',
            'sort_order' => '정렬 순서(수강 순서)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
        'visit_logs' => [
            'id' => '기본 키',
            'date' => '방문 일자(일별 집계 기준)',
            'ip' => '방문자 IP',
            'user_id' => '로그인 회원(비로그인 시 null)',
            'path' => '방문 경로(URL path)',
            'created_at' => '생성일시',
            'updated_at' => '수정일시',
        ],
    ];

    public function up(): void
    {
        if (! $this->supportsComments()) {
            return;
        }

        $this->applyComments(forward: true);
    }

    public function down(): void
    {
        if (! $this->supportsComments()) {
            return;
        }

        $this->applyComments(forward: false);
    }

    // `ALTER TABLE ... COMMENT`/`MODIFY COLUMN ... COMMENT`와 information_schema.columns 조회는
    // MySQL 문법이다 — 테스트 스위트는 속도 때문에 sqlite(:memory:)로 돌아가므로, 그 환경에서는
    // 조용히 건너뛴다(테스트 DB는 매번 새로 만들어지고 버려지므로 코멘트가 없어도 문제없음).
    private function supportsComments(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function applyComments(bool $forward): void
    {
        foreach (self::TABLE_COMMENTS as $table => $comment) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $value = $forward ? $comment : '';
            DB::statement("ALTER TABLE `{$table}` COMMENT = ".DB::getPdo()->quote($value));
        }

        foreach (self::COLUMN_COMMENTS as $table => $columns) {
            foreach ($columns as $column => $comment) {
                $this->commentColumn($table, $column, $forward ? $comment : '');
            }
        }
    }

    // MySQL은 COMMENT만 바꾸는 별도 문법이 없고 MODIFY COLUMN으로 컬럼 정의 전체를 다시
    // 선언해야 한다 — 그래서 여기서 하드코딩하는 대신 매 실행 시점에 information_schema에서
    // 실제 타입/NULL 허용/기본값/AUTO_INCREMENT를 그대로 읽어와 동일하게 재구성한 뒤 COMMENT만
    // 덧붙인다. 존재하지 않는 테이블/컬럼(dev 브랜치의 LMS 테이블 등)은 조용히 건너뛴다.
    private function commentColumn(string $table, string $column, string $comment): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $row = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        if (! $row) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$row->COLUMN_TYPE} ";
        $sql .= $row->IS_NULLABLE === 'NO' ? 'NOT NULL' : 'NULL';

        if (str_contains($row->EXTRA, 'auto_increment')) {
            $sql .= ' AUTO_INCREMENT';
        } elseif ($row->COLUMN_DEFAULT !== null) {
            $sql .= str_contains($row->EXTRA, 'DEFAULT_GENERATED')
                ? " DEFAULT {$row->COLUMN_DEFAULT}"
                : ' DEFAULT '.DB::getPdo()->quote($row->COLUMN_DEFAULT);
        } elseif ($row->IS_NULLABLE === 'YES') {
            $sql .= ' DEFAULT NULL';
        }

        $sql .= ' COMMENT '.DB::getPdo()->quote($comment);

        DB::statement($sql);
    }
};
