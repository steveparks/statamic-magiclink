<?php declare(strict_types=1);

namespace Codedge\MagicLink;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\YAML;

final class MagicLinkManager
{
    protected MagicLink $magicLink;
    protected Filesystem $files;
    protected string $path;
    protected User $user;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
        $this->path = storage_path('statamic-magiclink/magic-links.yaml');
    }

    public function createForUser(User $user): self
    {
        $this->user = $user;
        $this->magicLink = new MagicLink($user);

        return $this;
    }

    public function redirectTo(string $redirect): self
    {
        $this->magicLink->setRedirectTo($redirect);

        return $this;
    }

    public function withPath(string $path): self
    {
        $this->magicLink->setPath($path);

        return $this;
    }

    public function generate(): MagicLink
    {
        $link = $this->magicLink->generate();

        $payload[$this->user->email()] = [
            'expire_time' => $this->magicLink->getExpireTime(),
            'hash' => $link->getHash(),
        ];

        $this->save(collect($payload));

        return $link;
    }

    private function get(): Collection
    {
        if (! $this->files->exists($this->path)) {
            return collect();
        }

        return collect(YAML::parse($this->files->get($this->path)));
    }

    private function save(Collection $content)
    {
        if (! $this->files->isDirectory($dir = dirname($this->path))) {
            $this->files->makeDirectory($dir);
        }

        // Handle already existing entries and overwrite them with the new content
        // Each user can only have one magic link!
        $existing = $this->get();
        $merged = $existing->merge($content);

        return $this->files->put($this->path, YAML::dump($merged->all()));
    }


}
