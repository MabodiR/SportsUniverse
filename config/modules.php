<?php

return [
    // Concrete infrastructure adapters are declared here so business modules do
    // not need to import another module's persistence model directly.
    'media_model' => App\Domain\Media\Models\Media::class,
];
