<?php

namespace KD2\WebDAV;

use stdClass;

trait NextCloudNotes
{
	protected string $notes_directory = 'Notes';
	protected string $notes_suffix = '.md';

	protected function iterateNotes(?string $root, bool $recursive, bool $with_content, int $prune_before = 0): \Generator
	{
		$uri = $this->notes_directory;

		if ($root) {
			$uri .= '/' . $root;
		}

		$this->server->validateURI($uri);

		foreach ($this->storage->list($uri, null) as $name => $props) {
			$path = $uri . '/' . $name;

			$is_dir = current($this->storage->propfind($path, ['DAV::resourcetype'], 0));

			if ($is_dir) {
				if (!$recursive) {
					continue;
				}

				$category = substr($path, strlen($this->notes_directory . '/'));

				yield from $this->iterateNotes($category, $recursive, $with_content, $prune_before);
			}
			else {
				$note = $this->getNote($path, $with_content, $prune_before);

				if ($note) {
					yield $note;
				}
			}
		}
	}

	protected function findNotePath(int $id, string $root): ?string
	{
		$list = array_keys($this->storage->list($root, null));
		$dirs = [];

		// Process directories later, making it faster to find notes
		$list = array_filter($list, function ($path) use ($root, &$dirs) {
			$props = $this->storage->propfind($root . '/' . $path, ['DAV::resourcetype'], 0);
			$is_dir = !empty($props['DAV::resourcetype']);

			if ($is_dir) {
				$dirs[] = $path;
				return false;
			}

			return true;
		});

		foreach ($list as $file) {
			$path = $root . '/' . $file;
			$file_id = current($this->storage->propfind($path, [self::PROP_OC_ID], 0));

			if ($file_id == $id) {
				return $path;
			}
		}

		foreach ($dirs as $dir) {
			$found = $this->findNotePath($id, $root . '/' . $dir);

			if ($found) {
				return $found;
			}
		}

		return null;
	}

	protected function getNoteById(int $id, bool $with_content): ?stdClass
	{
		$path = $this->findNotePath($id, $this->notes_directory);

		if (!$path) {
			return null;
		}

		return $this->getNote($path, $with_content);
	}

	protected function getNote(string $path, bool $with_content = false, int $prune_before = 0): ?stdClass
	{
		if (!$this->storage->exists($path)) {
			return null;
		}

		static $requested_properties = [
			self::PROP_OC_ID,
			'DAV::getetag',
			'DAV::getlastmodified',
		];

		$props = $this->storage->propfind($path, $requested_properties, 0);

		if (!isset($props[self::PROP_OC_ID], $props['DAV::getetag'], $props['DAV::getlastmodified'])) {
			throw new \LogicException('Missing required properties for notes API');
		}

		$ts = $props['DAV::getlastmodified']->getTimestamp();

		if ($ts < $prune_before) {
			return (object) ['id' => (int)$props[self::PROP_OC_ID]];
		}

		$title = substr($path, strrpos($path, '/') + 1);
		$title = substr($title, 0, strrpos($title, '.'));
		$category = substr($path, strlen($this->notes_directory . '/'));
		$category = substr($category, 0, strrpos($category, '/'));

		$data = (object) [
			'id'       => (int) $props[self::PROP_OC_ID],
			'etag'     => $props['DAV::getetag'],
			'readonly' => false, // unsupported
			'title'    => $title,
			'category' => $category,
			'favorite' => false, // unsupported
			'modified' => $ts,
			'_path'    => $path,
		];

		if ($with_content) {
			$data->content = $this->storage->fetch($path);
		}

		return $data;
	}

	protected function writeNote(?stdClass $note): stdClass
	{
		$data = json_decode(file_get_contents('php://input'));

		if ($note) {
			$data->title ??= $note->title;
			$data->category ??= $note->category;
		}
		elseif (!isset($data->title, $data->category, $data->content)) {
			throw new Exception('Missing required key', 400);
		}

		$path = $this->notes_directory . '/';

		if ($data->category) {
			$path .= $data->category . '/';
		}

		$path .= $data->title . '.md';

		$this->server->validateURI($path);

		if ($note) {
			// If the note category or title have changed, we need to move the note
			if ($note->category !== $data->category
				|| $note->title !== $data->title) {
				$this->storage->move($note->_path, $path);
			}

			if (!isset($data->content)
				|| $data->content === $note->content) {
				// No other changes, stop here
				http_response_code(200);
				return $this->getNote($path, true);
			}
		}

		if (!$note && $this->storage->exists($path)) {
			// Not sure why, but sometimes app, is trying to create a new note,
			// instead of writing to existing note
			//throw new Exception('This note already exists', 409);
		}

		$fp = fopen('php://temp', 'w+');
		fwrite($fp, $data->content);
		rewind($fp);
		$this->storage->put($path, $fp);

		http_response_code(200);
		return $this->getNote($path, true);
	}

	protected function nc_notes(string $uri)
	{
		$this->requireAuth();

		$last = substr(rtrim($uri, '/'), strrpos($uri, '/') + 1);
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		$this->server->prefix = '';

		if ($last === 'settings') {
			if ($method === 'PUT') {
				throw new Exception('This is not implemented currently', 405);
			}
			elseif ($method === 'GET') {
				return ['notesPath' => $this->notes_directory, 'fileSuffix' => $this->notes_suffix];
			}
		}
		elseif ($last === 'notes') {
			if ($method === 'GET') {
				$exclude = isset($_GET['exclude']) ? explode(',', $_GET['exclude']) : [];
				$with_content = !in_array('content', $exclude);
				$prune_before = intval($_GET['pruneBefore'] ?? 0);
				$root = $_GET['category'] ?? null;
				$recursive = $root ? false : true;

				$props = $this->storage->propfind($this->notes_directory, ['DAV::getetag', 'DAV::getlastmodified'], 1);

				http_response_code(200);

				if (isset($props['DAV::getetag'])) {
					header('ETag: ' . $props['DAV::getetag']);
				}

				if (isset($props['DAV::getlastmodified'])) {
					header('Last-Modified: ' . $props['DAV::getlastmodified']->format(\DATE_RFC7231));
				}

				$notes = $this->iterateNotes($root, $recursive, $with_content, $prune_before);
				$notes = iterator_to_array($notes, false);
				return $notes;
			}
			elseif ($method === 'POST') {
				return $this->writeNote(null);
			}
			else {
				throw new Exception('Invalid method', 405);
			}
		}
		elseif (ctype_digit($last)) {
			if ($method === 'GET') {
				$note = $this->getNoteById((int)$last, true);

				if (!$note) {
					throw new Exception('Unknown note ID', 404);
				}

				return $note;
			}
			elseif ($method === 'PUT') {
				$note = $this->getNoteById((int)$last, true);

				if (!$note) {
					throw new Exception('Unknown note ID', 404);
				}

				return $this->writeNote($note);
			}
			elseif ($method === 'DELETE') {
				$note = $this->getNoteById((int)$last, false);

				if (!$note) {
					throw new Exception('Unknown note ID', 404);
				}

				$this->storage->delete($note->_path);
				http_response_code(200);
				return null;
			}
			else {
				throw new Exception('Invalid method', 405);
			}
		}
		else {
			throw new Exception('Invalid URL', 404);
		}
	}
}
