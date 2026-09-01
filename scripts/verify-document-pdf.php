<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$document=new \App\Models\Document(['type'=>'proposal','title'=>'Proposta de Gestão · Conferência Horizonte','status'=>'draft','purpose'=>'management','version'=>1,'content'=>['sections'=>['objective'=>'Reunir a equipe para compartilhar resultados e planejar o próximo ciclo.','scope'=>"Iluminação do palco e recepção.\nEquipe técnica de apoio durante o evento.\nMontagem e desmontagem acompanhadas pelo produtor responsável.",'conditions'=>'Dados fictícios para verificar o documento. Valores, escopo e condições ainda dependem de revisão humana.'],'sources'=>['case_title'=>'Conferência Horizonte 2026','client'=>'Cliente fictício','briefing_revision'=>2,'budget_id'=>1,'budget_revision'=>3,'budget'=>['totalCents'=>1250000]]]]);
$directory=__DIR__.'/../storage/app/private/pdf-verification';
if(!is_dir($directory))mkdir($directory,0700,true);
file_put_contents($directory.'/proposal.pdf',app(\App\Services\DocumentRevisions::class)->pdf($document));
echo $directory.'/proposal.pdf'.PHP_EOL;
