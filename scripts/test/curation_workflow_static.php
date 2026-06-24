<?php

declare(strict_types=1);

$root=dirname(__DIR__,2);
$checks=[
'database/migrations/0048_curation_workflow.sql'=>['curation_items','user_messages','curation_workflow_included'],
'app/Tenant/Curation/CurationRepository.php'=>['function workflowEnabled','function review','INSERT INTO user_messages'],
'app/Http/Controllers/Tenant/CurationController.php'=>['Add to curation','/admin/curation/review','function messages'],
'app/Tenant/Artwork/ArtworkReadRepository.php'=>['includeUnpublished','include_unpublished'],
'app/Http/Controllers/Platform/PricingController.php'=>['Curation workflow','custom_domain_included'],
];
foreach($checks as $file=>$needles){$text=file_get_contents($root.'/'.$file);if($text===false){fwrite(STDERR,"Missing {$file}\n");exit(1);}foreach($needles as $needle){if(!str_contains($text,$needle)){fwrite(STDERR,"Missing {$needle} in {$file}\n");exit(1);}}}
echo "Curation workflow static checks passed.\n";

// End of file.
