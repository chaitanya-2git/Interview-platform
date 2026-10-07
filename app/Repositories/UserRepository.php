<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Support\Database;
final class UserRepository
{
    public function byId(int $id): ?array
    {
        $query = Database::connection()->prepare('SELECT id, role, full_name, phone, email, username, active, created_at FROM users WHERE id = ? AND active = 1');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }
    public function byIdentity(string $identity): ?array
    {
        $phone = \App\Validation\Input::phone($identity) ?? $identity;
        $query = Database::connection()->prepare('SELECT * FROM users WHERE phone = ? OR (role = \'ADMIN\' AND (email = ? OR username = ?)) LIMIT 1');
        $query->execute([$phone, $identity, $identity]);
        return $query->fetch() ?: null;
    }
    public function profile(int $id): ?array
    {
        $db=Database::connection();$query=$db->prepare('SELECT p.*,r.original_filename resume_original_filename,r.mime_type resume_mime_type,r.file_size resume_file_size,r.uploaded_at resume_uploaded_at,r.updated_at resume_updated_at FROM candidate_profiles p LEFT JOIN candidate_resumes r ON r.candidate_id=p.user_id WHERE p.user_id=?');
        $query->execute([$id]);
        $profile=$query->fetch();if(!$profile)return null;$profile['tech_stack']=(new CandidateTechnologyRepository($db))->stack($id);$profile['tech_stack_label']=$profile['tech_stack']?implode(' · ',array_column($profile['tech_stack'],'name')):'Tech stack not added yet';return $profile;
    }
    public function technologies(): array { return (new TechnologyRepository(Database::connection()))->picker(true); }
}
