<?php
declare(strict_types=1);
namespace App\Support;
final class Ui
{
    public static function icon(string $name, string $class = ''): string
    {
        $paths = [
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
            'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
            'monitor' => '<rect x="3" y="3" width="18" height="13" rx="2"/><path d="M8 21h8m-4-5v5"/>',
            'layers' => '<path d="m12 3 10 5-10 5L2 8zm-10 9 10 5 10-5M2 16l10 5 10-5"/>',
            'logout' => '<path d="M9 4H4v16h5m5-13 5 5-5 5M8 12h11"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>',
            'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
            'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4z"/>',
            'trash' => '<path d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7m4 4v6m4-6v6"/>',
            'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/>',
            'chevron' => '<path d="m9 18 6-6-6-6"/>',
            'chart' => '<path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>',
            'activity' => '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
        ];
        return '<svg class="icon ' . View::escape($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['grid']) . '</svg>';
    }
    public static function csrf(): string { return '<input type="hidden" name="_token" value="' . View::escape(Session::token()) . '">'; }
    public static function error(array $errors, string $key): string { return isset($errors[$key]) ? '<p class="field-error" id="' . View::escape($key) . '-error">' . View::escape($errors[$key]) . '</p>' : ''; }
    public static function invalid(array $errors, string $key): string { return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . View::escape($key) . '-error"' : ''; }
    public static function avatar(array $user, ?array $profile = null, string $class = ''): string
    {
        return self::avatarMarkup((string)$user['full_name'],$profile,$class,null);
    }
    public static function candidateAvatar(array $candidate,string $class=''):string
    {
        $id=filter_var($candidate['candidate_id']??$candidate['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        return self::avatarMarkup((string)($candidate['candidate_name']??$candidate['full_name']??'Deleted candidate'),$candidate,$class,$id?(int)$id:null);
    }
    private static function avatarMarkup(string $name,?array $profile,string $class,?int $candidateId):string
    {
        $filename=is_string($profile['photo_filename']??null)?$profile['photo_filename']:'';$photo=false;
        if($candidateId!==null&&preg_match('/^[a-f0-9]{48}\.jpg$/',$filename)){$directory=realpath(ROOT.'/storage/uploads');$photo=$directory!==false&&is_file($directory.DIRECTORY_SEPARATOR.$filename);}
        $body=View::escape(View::initials($name));
        if($photo){$query=['id'=>$candidateId,'v'=>substr(hash('sha256',$filename),0,12)];$body='<img src="'.View::escape(View::url('profile/photo?'.http_build_query($query))).'" alt="'.View::escape($name).'">';}
        elseif($candidateId===null&&!empty($profile['photo_filename'])&&preg_match('/^[a-f0-9]{48}\.jpg$/',$filename)){$directory=realpath(ROOT.'/storage/uploads');if($directory!==false&&is_file($directory.DIRECTORY_SEPARATOR.$filename)){$body='<img src="'.View::escape(View::url('profile/photo?v='.substr(hash('sha256',$filename),0,12))).'" alt="'.View::escape($name).'">';}}
        return '<span class="avatar '.View::escape($class).'">'.$body.'</span>';
    }
    public static function pagination(array $result,array $filters,string $label):string
    {
        $pages=(int)$result['pages'];$current=(int)$result['page'];if($pages<=1)return '';$shown=array_unique(array_filter([1,$current-2,$current-1,$current,$current+1,$current+2,$pages],static fn($p)=>$p>=1&&$p<=$pages));sort($shown);$html='<nav class="pagination" aria-label="'.View::escape($label).'">';$previous=0;
        foreach($shown as $page){if($previous&&$page>$previous+1)$html.='<span aria-hidden="true">…</span>';$href='?'.http_build_query(array_merge($filters,['page'=>$page]));$html.='<a class="'.($page===$current?'active':'').'" '.($page===$current?'aria-current="page" ':'').'href="'.View::escape($href).'">'.$page.'</a>';$previous=$page;}
        return $html.'</nav>';
    }
}
