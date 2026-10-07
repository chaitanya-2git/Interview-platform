<?php
declare(strict_types=1);
namespace App\Services;

use App\Validation\ValidationException;
use PDO;

final class ResumeService
{
    public const MAX_BYTES=5*1024*1024;
    private const PDF_MIME='application/pdf';
    private const DOCX_MIME='application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function __construct(private readonly ?PDO $db=null,private readonly ?AuditService $audit=null){}

    public function storeUploaded(?array $file):?array
    {
        if($file===null||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
        $error=$file['error']??null;
        if($error===UPLOAD_ERR_INI_SIZE||$error===UPLOAD_ERR_FORM_SIZE)throw new ValidationException(['resume'=>'The resume is larger than the server upload limit. Choose a PDF or DOCX up to 5 MB.']);
        if($error!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new ValidationException(['resume'=>'The resume could not be uploaded. Choose a PDF or DOCX up to 5 MB and try again.']);
        $meta=$this->validate($file['tmp_name'],is_string($file['name']??null)?$file['name']:'resume');
        $directory=ROOT.'/storage/resumes';
        if(!is_dir($directory)||!is_writable($directory))throw new \RuntimeException('Resume storage unavailable');
        $stored=bin2hex(random_bytes(24)).'.'.$meta['extension'];
        if(!move_uploaded_file($file['tmp_name'],$directory.DIRECTORY_SEPARATOR.$stored))throw new \RuntimeException('Resume storage unavailable');
        return $meta+['stored_filename'=>$stored];
    }

    public function validate(string $path,string $originalName):array
    {
        if(!is_file($path))throw new ValidationException(['resume'=>'Choose a PDF or DOCX resume up to 5 MB.']);
        $size=filesize($path);
        if(!is_int($size)||$size<1||$size>self::MAX_BYTES)throw new ValidationException(['resume'=>'Choose a PDF or DOCX resume up to 5 MB.']);
        $display=$this->displayFilename($originalName);
        if(preg_match('/\.(?:php\d*|phtml|phar|js|html?|svg|exe|bat|cmd|com|zip|rar)\.(?:pdf|docx)$/i',$display))throw new ValidationException(['resume'=>'Choose a file with a single .pdf or .docx extension.']);
        $extension=strtolower(pathinfo($display,PATHINFO_EXTENSION));
        if(!in_array($extension,['pdf','docx'],true))throw new ValidationException(['resume'=>'Only PDF and DOCX resumes are allowed.']);
        $detected=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if($extension==='pdf'){
            if($detected!==self::PDF_MIME||!$this->validPdf($path,$size))throw new ValidationException(['resume'=>'This file is not a valid PDF resume.']);
            $mime=self::PDF_MIME;
        }else{
            if(!in_array($detected,['application/zip',self::DOCX_MIME],true)||!$this->validDocx($path,$size))throw new ValidationException(['resume'=>'This file is not a valid DOCX resume.']);
            $mime=self::DOCX_MIME;
        }
        return ['original_filename'=>$display,'mime_type'=>$mime,'file_size'=>$size,'extension'=>$extension];
    }

    public function replace(int $candidateId,int $actorId,?array $file):void
    {
        if(!$this->db)throw new \LogicException('Database required');
        $new=$this->storeUploaded($file);
        if(!$new)throw new ValidationException(['resume'=>'Choose a PDF or DOCX resume to upload.']);
        $old=null;$replaced=false;
        try{
            $this->db->beginTransaction();
            $q=$this->db->prepare("SELECT p.user_id,r.stored_filename FROM candidate_profiles p JOIN users u ON u.id=p.user_id LEFT JOIN candidate_resumes r ON r.candidate_id=p.user_id WHERE p.user_id=? AND u.role='CANDIDATE' FOR UPDATE");
            $q->execute([$candidateId]);$row=$q->fetch();if(!$row)throw new ValidationException(['resume'=>'Candidate profile not found.']);
            $old=is_string($row['stored_filename']??null)?$row['stored_filename']:null;$replaced=$old!==null;
            $q=$this->db->prepare('INSERT INTO candidate_resumes(candidate_id,stored_filename,original_filename,mime_type,file_size) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE stored_filename=VALUES(stored_filename),original_filename=VALUES(original_filename),mime_type=VALUES(mime_type),file_size=VALUES(file_size),uploaded_at=UTC_TIMESTAMP()');
            $q->execute([$candidateId,$new['stored_filename'],$new['original_filename'],$new['mime_type'],$new['file_size']]);
            ($this->audit??new AuditService($this->db))->record($actorId,$replaced?'candidate.resume_replaced':'candidate.resume_uploaded','user',$candidateId,['mime_type'=>$new['mime_type'],'file_size'=>$new['file_size']]);
            $this->db->commit();
        }catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();$this->remove($new['stored_filename']);throw $error;}
        if($old!==null&&$old!==$new['stored_filename'])$this->removeIfUnreferenced($old);
    }

    public function removeIfUnreferenced(?string $stored):bool
    {
        if($stored===null)return true;
        if($this->db){$q=$this->db->prepare('SELECT COUNT(*) FROM candidate_resumes WHERE stored_filename=?');$q->execute([$stored]);if((int)$q->fetchColumn()>0)return true;}
        return $this->remove($stored);
    }

    public function remove(?string $stored):bool
    {
        if($stored===null)return true;
        if(!preg_match('/^[a-f0-9]{48}\.(?:pdf|docx)$/',$stored))return false;
        $directory=realpath(ROOT.'/storage/resumes');if($directory===false)return false;
        $path=$directory.DIRECTORY_SEPARATOR.$stored;
        return !is_file($path)||@unlink($path);
    }

    public function path(string $stored):?string
    {
        if(!preg_match('/^[a-f0-9]{48}\.(?:pdf|docx)$/',$stored))return null;
        $directory=realpath(ROOT.'/storage/resumes');if($directory===false)return null;
        $path=$directory.DIRECTORY_SEPARATOR.$stored;return is_file($path)?$path:null;
    }

    public function contentDisposition(string $filename,bool $inline):string
    {
        $safe=$this->displayFilename($filename);$extension=strtolower(pathinfo($safe,PATHINFO_EXTENSION));
        $fallback=preg_replace('/[^A-Za-z0-9._-]+/','_',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$safe)?:'resume.'.$extension);
        $fallback=trim((string)$fallback,'._-');if($fallback===''||!str_ends_with(strtolower($fallback),'.'.$extension))$fallback='resume.'.$extension;
        $fallback=substr($fallback,0,120);
        return ($inline?'inline':'attachment').'; filename="'.addcslashes($fallback,'"\\').'"; filename*=UTF-8\'\''.rawurlencode($safe);
    }

    private function displayFilename(string $name):string
    {
        if(str_contains($name,"\0")||str_contains($name,"\r")||str_contains($name,"\n"))throw new ValidationException(['resume'=>'The resume filename contains invalid characters.']);
        $name=str_replace('\\','/',$name);$name=trim(basename($name));
        $name=preg_replace('/[\x00-\x1f\x7f]/u','',$name)??'';
        if($name===''||mb_strlen($name)>190)throw new ValidationException(['resume'=>'Use a resume filename of 190 characters or fewer.']);
        return $name;
    }

    private function validPdf(string $path,int $size):bool
    {
        $handle=fopen($path,'rb');if(!$handle)return false;
        $start=fread($handle,8);fseek($handle,max(0,$size-2048));$tail=stream_get_contents($handle);fclose($handle);
        return str_starts_with((string)$start,'%PDF-')&&str_contains((string)$tail,'%%EOF');
    }

    private function validDocx(string $path,int $size):bool
    {
        $handle=fopen($path,'rb');if(!$handle)return false;
        fseek($handle,max(0,$size-65557));$tail=stream_get_contents($handle);$tailStart=max(0,$size-65557);$relative=strrpos((string)$tail,"PK\x05\x06");
        if($relative===false){fclose($handle);return false;}$eocd=$tailStart+$relative;fseek($handle,$eocd+4);$raw=fread($handle,18);$end=$raw!==false?unpack('vdisk/vstart_disk/ventries_disk/ventries/Vsize/Voffset/vcomment',$raw):false;
        if(!$end||$end['disk']!==0||$end['start_disk']!==0||$end['entries']<3||$end['entries']>1000||$end['size']>2*1024*1024||$end['offset']+$end['size']>$eocd){fclose($handle);return false;}
        fseek($handle,$end['offset']);$entries=[];$total=0;
        for($i=0;$i<$end['entries'];$i++){
            $fixed=fread($handle,46);if($fixed===false||strlen($fixed)!==46||substr($fixed,0,4)!=="PK\x01\x02"){fclose($handle);return false;}
            $h=unpack('vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Vlocal_offset',substr($fixed,4));if(!$h||$h['name_len']<1||$h['name_len']>500||($h['flags']&1)||!in_array($h['method'],[0,8],true)){fclose($handle);return false;}
            $name=fread($handle,$h['name_len']);if($name===false||str_contains($name,"\0")||str_contains($name,'\\')||str_starts_with($name,'/')||in_array('..',explode('/',$name),true)){fclose($handle);return false;}
            fseek($handle,$h['extra_len']+$h['comment_len'],SEEK_CUR);$total+=$h['uncompressed'];
            if($h['uncompressed']>10*1024*1024||$total>50*1024*1024||($h['compressed']>0&&$h['uncompressed']/$h['compressed']>100)){fclose($handle);return false;}
            if(str_ends_with(strtolower($name),'vbaproject.bin')){fclose($handle);return false;}
            $entries[$name]=$h;
        }
        foreach(['[Content_Types].xml','_rels/.rels','word/document.xml'] as $required)if(!isset($entries[$required])){fclose($handle);return false;}
        $types=$this->zipEntry($handle,$entries['[Content_Types].xml']);$document=$this->zipEntry($handle,$entries['word/document.xml']);fclose($handle);
        return is_string($types)&&is_string($document)&&str_contains($types,self::DOCX_MIME.'.main+xml')&&!str_contains(strtolower($types),'macroenabled')&&str_contains($document,'<w:document');
    }

    private function zipEntry($handle,array $entry):?string
    {
        fseek($handle,$entry['local_offset']);$fixed=fread($handle,30);if($fixed===false||strlen($fixed)!==30||substr($fixed,0,4)!=="PK\x03\x04")return null;
        $lengths=unpack('vname/vextra',substr($fixed,26,4));if(!$lengths)return null;
        fseek($handle,$lengths['name']+$lengths['extra'],SEEK_CUR);$compressed=fread($handle,$entry['compressed']);if($compressed===false||strlen($compressed)!==$entry['compressed'])return null;
        $data=$entry['method']===0?$compressed:@gzinflate($compressed,$entry['uncompressed']);
        if(!is_string($data)||strlen($data)!==$entry['uncompressed']||sprintf('%u',crc32($data))!==sprintf('%u',$entry['crc']))return null;
        return $data;
    }
}
