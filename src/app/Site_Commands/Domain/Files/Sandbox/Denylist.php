<?php
/**
 * Paths whose content file commands must not read.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Glob_Pattern;

/**
 * Paths inside the sandbox whose CONTENT must not be read.
 *
 * Nothing is invisible. Malware hides in dotfiles and odd directories, so
 * every path stays visible to ls, find, stat, and du. What the denylist
 * refuses is content: cat, head, tail, sed, grep, wc, and checksums will
 * not open a denied file.
 *
 * Five kinds of rule, all closed sets that do not drift:
 *
 * - **Directory rules** match any single path segment. Everything under a
 *   matching directory is content-denied. Used where the directory name
 *   is distinctive (`w3tc-config`, `wflogs`, `updraft`, `.git`).
 * - **Path rules** are globs over the whole absolute path, for directory
 *   names that collide with plugin directories (`uploads/wp-staging` holds
 *   backups; `plugins/wp-staging` is code the agent must read).
 * - **File rules** match the base name, both as written and after backup
 *   suffixes are stripped, so `wp-config.php.bak`, `.wp-config.php.swp`,
 *   `wp-config-old.php`, and `#wp-config.php#` fall under the rule for
 *   `wp-config.php`.
 * - **Site-root rules** are file and directory names that matter only in
 *   WordPress's own folder, where migrations and backup tools leave their
 *   installers (`installer.php`, `dup-installer`). Plugin code reuses those
 *   names (`src/Blocks/Installer.php`) and stays readable. While the site's
 *   folder is unknown they apply everywhere.
 * - **Content signatures** catch a private key or a service-account file
 *   regardless of its name. This is a closed set of headers, not a secret
 *   scanner; the scanner lives on the backend. They judge what a command
 *   prints to the caller, so they also mark where a key ends, for text
 *   printed from after its header. Trade-off: they never stop a file being
 *   opened and they cannot catch every credential, so the tool stays useful
 *   and blocking a sensitive file is left to its name or the owner's settings.
 *
 * Deny what holds credentials, allow what explains behaviour. `.htaccess`,
 * `.user.ini`, `debug.log`, the cache drop-ins, and hidden PHP files stay
 * readable on purpose. The research behind every entry is in the plugin
 * plan workspace (DENYLIST-RESEARCH.md).
 */
final class Denylist {

	/**
	 * Longest file or folder entry a site owner may add.
	 */
	public const MAX_OWNER_ENTRY_BYTES = 255;

	/**
	 * Any path segment. Distinctive names only; no clashes with plugin
	 * directory names.
	 */
	public const DEFAULT_DIRECTORIES = array(
		// version control, shells, cloud CLIs, editors
		'.git',
		'.svn',
		'.hg',
		'.ssh',
		'.aws',
		'.gnupg',
		'.azure',
		'.kube',
		'.docker',
		'.password-store',
		'.direnv',
		'.terraform',
		'.wp-cli',
		'.composer',
		'.gem',
		'.bundle',
		'.idea',
		'.vscode',
		// hosting
		'_wpeprivate',
		// caching and security plugins that write keys to disk
		'w3tc-config',
		'wp-rocket-config',
		'wflogs',
		'config-*-nitropack',
		// backup, migration, staging artefacts
		'updraft',
		'backups-dup-lite',
		'backups-dup-pro',
		'wp-snapshots',
		'ai1wm-backups',
		'wpvividbackups',
		'aiowps_backups',
		'bps-backup',
		'backup-migration',
		'backup-db',
		'boldgrid_backup',
		'backwpup-*-backups',
		'backwpup-*-logs',
		'backwpup-*-temp',
		'backwpup-restore',
		'xcloner-*',
	);

	/**
	 * Globs over the absolute path; `*` spans separators.
	 */
	public const DEFAULT_PATHS = array(
		'*/uploads/wp-staging/*',
		'*/uploads/wp-migrate-db/*',
		'*/uploads/wpmdb/*',
		'*/uploads/private/*',
		'*/uploads/sucuri/*',
		'*/uploads/mainwp/*',
		'*/uploads/backupbuddy_backups/*',
		'*/uploads/backupbuddy_temp/*',
		'*/uploads/ithemes-security/backups/*',
		'*/uploads/ithemes-security/logs/*',
		'*/wp-content/database/*',
		'*/all-in-one-wp-migration/storage/*',
		'*/managewp/backups/*',
		'*/infinitewp/backups/*',
		'*/ssl/keys/*',
		'*/ssl_zen/keys/*',
		'*/.ddev/db_snapshots/*',
		'*/nbproject/private/*',
		'*/.config/gcloud/*',
		'*/.config/gh/*',
		'*/.config/rclone/*',
		'*/.terraform.d/*',
	);

	/**
	 * Base names, matched case-insensitively, before and after backup
	 * suffixes are stripped.
	 */
	public const DEFAULT_FILES = array(
		// WordPress configuration and host-specific config
		'*wp-config*',
		'db-config.php',
		'local-config.php',
		'wp-salt.php',
		'wp-salts.php',
		'gd-config.php',
		'wp-tests-config.php',
		'.wp-env.override.json',
		'.lando.local.yml',
		// plugin-written settings with keys or credentials
		'civicrm.settings.php',
		'sucuri-settings.php',
		'.litespeed_conf.dat',
		// dumps and archives that a text reader could open
		'*.sql',
		'*.sql.*',
		'*.mysql',
		'*.dump',
		'*-db.gz',
		'*.wpress',
		'*.wpstg',
		'*.daf',
		'*.tar',
		'*.tar.*',
		'*.tgz',
		'*.tbz2',
		'*.txz',
		'*.gz',
		'*.bz2',
		'*.xz',
		'*.zst',
		'*.zip',
		'*.7z',
		'*.rar',
		'*.cpio',
		'*.sqlite',
		'*.sqlite3',
		'dup-scan__*.json',
		// environment and dotenv
		'.env*',
		'*.env',
		'.envrc',
		'*docker-compose*.y*ml',
		'compose*.y*ml',
		// HTTP auth
		'.htpasswd*',
		'.htdigest*',
		// package manager and CLI credentials
		'auth.json',
		'.npmrc',
		'.yarnrc*',
		'.pypirc',
		'.netrc',
		'_netrc',
		'.git-credentials',
		'.gitconfig',
		'.s3cfg',
		'.boto',
		'.dockercfg',
		'.terraformrc',
		'.vault-token',
		'.vault_pass*',
		'wp-cli*.yml',
		'.my.cnf',
		'.mylogin.cnf',
		'.pgpass',
		'.pg_service.conf',
		// editor and transfer-client configs with stored passwords
		'sftp-config*.json',
		'sftp.json',
		'.remote-sync.json',
		'.ftpconfig',
		'.ftppass',
		'deployment-config.json',
		'*.ste',
		'winscp.ini',
		'sitemanager.xml',
		'recentservers.xml',
		'filezilla.xml',
		// shell and REPL histories
		'.*_history',
		'.history',
		'.viminfo',
		// keys, certificates with private material, keystores, password databases
		'*.pem',
		'*.key',
		'*.p12',
		'*.pfx',
		'*.jks',
		'*.ppk',
		'*.der',
		'*.p8',
		'*.pk8',
		'*.keystore',
		'*.bks',
		'*.pkcs12',
		'*.gpg',
		'*.pgp',
		'*.kdb',
		'*.kdbx',
		'*.psafe3',
		'*.ovpn',
		'*.tblk',
		'*.rdp',
		'id_rsa',
		'id_rsa.*',
		'id_ed25519',
		'id_ed25519.*',
		'id_ecdsa',
		'id_ecdsa.*',
		'id_dsa',
		'id_dsa.*',
		// cloud and API credential files
		'client_secret*.json',
		'service_account*.json',
		'*service-account*.json',
		'*service_account*.json',
		'firebase-adminsdk-*.json',
		'google-credentials*.json',
		'*credentials*.json',
		'token.json',
		'token.pickle',
		// infrastructure state
		'*.tfstate',
		'*.tfstate.*',
		'*.tfvars',
		'*.tfvars.json',
		// the obviously named
		'secrets.*',
		'.secrets*',
		'.secret',
		'*.secret',
		'master.key',
		'database.yml',
		'parameters.yml',
		'passwords.txt',
		'password.txt',
		'.passwd',
		'passwd',
		'creds.txt',
		'credentials.txt',
		'*.token',
		'.token',
		'config.inc.php',
		'config.local.php',
		'config-local.php',
		'settings.local.php',
		'LocalSettings.php',
	);

	/**
	 * Base names that matter only in WordPress's own folder: installers and
	 * connectors dropped there by migration, backup and security tools.
	 */
	public const DEFAULT_SITE_ROOT_FILES = array(
		'installer.php',
		'*_installer.php',
		'installer-backup.php',
		'importbuddy.php',
		'bv_connector_*.php',
		'malcare-waf.php',
	);

	/**
	 * Directory names that matter only in WordPress's own folder.
	 */
	public const DEFAULT_SITE_ROOT_DIRECTORIES = array(
		'dup-installer',
	);

	/**
	 * Base names a file rule would catch but which are public templates.
	 * Checked as written and after normalisation.
	 */
	public const DEFAULT_FILE_EXCEPTIONS = array(
		'wp-config-sample.php',
		'wp-config-pantheon.php',
		'local-config-sample.php',
		'.env.example',
		'.env.sample',
		'.env.template',
		'.env-sample',
		'.env-example',
		'.env.dist.example',
	);

	/**
	 * The example keys and salts WordPress core documents in wp_salt()
	 * (wp-includes/pluggable.php), as regex alternatives. They are public
	 * text in every WordPress install, not credentials, so a command must
	 * print them as GNU would.
	 */
	private const CORE_EXAMPLE_KEYS = ' Xakm\\<o xQy rw4EMsLKM\\-\\?\\!T\\+,PFF\\}\\)H4lzcW57AF0U@N@\\< \\>M%G4Yt\\>f`z\\]MON'
		. '|LzJ\\}op\\]mr\\|6\\+\\!\\[P\\}Ak\\:uNdJCJZd\\>\\(Hx\\.\\-Mh\\#Tz\\)pCIU\\#uGEnfFz\\|f ;;eU%\\/U\\^O~'
		. '|\\|i\\|Ux`9\\<p\\-h\\$aFf\\(qnT\\:sDO\\:D1P\\^wZ\\$\\$\\/Ra@miTJi9G;ddp_\\<q\\}6H1\\)o\\|a \\+&JCM'
		. '|%\\:R\\{\\[P\\|,s\\.KuMltH5\\}cI;\\/k\\<Gx~j\\!f0I\\)m_sIyu\\+&NJZ\\)\\-iO\\>z7X\\>QYR0Z_XnZ@\\|'
		. '|eZyT\\)\\-Naw\\]F8CwA\\*VaW\\#q\\*\\|\\.\\)g@o\\}\\|\\|wf~@C\\-YSt\\}\\(dh_r6EbI\\#A,y\\|nU2\\{B\\#JBW'
		. '|\\!\\=oLUTXh,QW\\=H `\\}`L\\|9\\/\\^4\\-3 STz\\},T\\(w\\}W\\<I`\\.JjPi\\)\\<Bmf1v,HpGe\\}T1\\:Xt7n'
		. '|\\+XSqHc;@Q\\*K_b\\|Z\\?NC\\[3H\\!\\!EONbh\\.n\\<\\+\\=uKR\\:\\>\\*c\\(u`g~EJBf\\#8u\\#R\\{mUEZrozmm'
		. '|h`GXHhD\\>SLWVfg1\\(1\\(N\\{;\\.V\\!MoE\\(SfbA_ksP@&`\\+AycHcAV\\$\\+\\?@3q\\+rxV\\{%\\^VyKT';

	/**
	 * Headers that identify a credential file whatever it is called.
	 */
	public const DEFAULT_CONTENT_SIGNATURES = array(
		// Renamed WordPress configuration and common dotenv credential copies.
		// Match assignments, not mere mentions of constant names in source.
		// Only the exact, literal public WordPress placeholders and core's
		// documented examples are exempt. Expressions, incomplete definitions
		// and every other assignment stay denied.
		'/\bdefine\s*\(\s*[\'"]DB_PASSWORD[\'"]\s*,(?!\s*([\'"])(?-i:password_here)\1\s*\))/i',
		'/\bdefine\s*\(\s*[\'"](?:AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY|AUTH_SALT|SECURE_AUTH_SALT|LOGGED_IN_SALT|NONCE_SALT)[\'"]\s*,(?!\s*([\'"])(?-i:put your unique phrase here|' . self::CORE_EXAMPLE_KEYS . ')\1\s*\))/i',
		'/^\s*(?:export\s+)?(?:DB_PASSWORD|DATABASE_PASSWORD|MYSQL_PASSWORD|AWS_SECRET_ACCESS_KEY|API_KEY|API_SECRET|ACCESS_TOKEN|PRIVATE_KEY)\s*=/m',
		'/^-----BEGIN (?:RSA |OPENSSH |EC |DSA |PGP |ENCRYPTED )?PRIVATE KEY(?: BLOCK)?-----\s*$/m',
		// A key's last line, for text printed from after its first.
		'/^-----END (?:RSA |OPENSSH |EC |DSA |PGP |ENCRYPTED )?PRIVATE KEY(?: BLOCK)?-----\s*$/m',
		'/^PuTTY-User-Key-File-\d/m',
		// The line that opens a PuTTY key's private part.
		'/^Private-Lines:\s*\d+\s*$/m',
		'/"type"\s*:\s*"service_account"/',
		// A service-account file's key, for text printed without its "type" line.
		'/"private_key"\s*:\s*"-----BEGIN (?:RSA |EC |DSA |ENCRYPTED )?PRIVATE KEY-----/',
	);

	/**
	 * Suffixes editors, admins, and backup scripts append to a copy.
	 */
	private const BACKUP_SUFFIX = '/^(.+?)(?:~|!|\.(?:bak|bk|bkp|backup|back|old|orig|original|prev|save|sav|copy|cp|tmp|temp|new|rej|dist|disabled|off|swp|swo|swn|kate-swp|txt|zip|gz|tgz|tar|rar|7z|un~|\d{1,2}|\d{8}|\d{4}-\d{2}-\d{2}|bak\d+)|[-_](?:bak|old|backup|orig|copy|original|\d{1,2}))$/i';

	/**
	 * A backup marker before the extension, such as wp-config-old.php.
	 */
	private const BACKUP_INFIX = '/^(.+?)[-_.](?:bak|backup|old|orig|copy|save|new|original|\d{1,3})(\.[a-z0-9]{1,5})$/i';

	/**
	 * A backup marker before the name, such as backup-wp-config.php.
	 */
	/**
	 * Most names one copy's name is checked as; a name that strips to more
	 * is refused.
	 */
	private const MAX_NAME_STEPS = 256;

	private const BACKUP_PREFIX = '/^(?:copy-of|backup|bak|old|copy|orig)[-_.](.+)$/i';

	/**
	 * A vim swap or undo file.
	 */
	private const VIM_SWAP = '/^\.(.+)\.(?:swp|swo|swn|swm|un~)$/i';

	/**
	 * Directory rules.
	 *
	 * @var string[]
	 */
	private array $directories;

	/**
	 * Path rules.
	 *
	 * @var string[]
	 */
	private array $paths;

	/**
	 * File rules.
	 *
	 * @var string[]
	 */
	private array $files;

	/**
	 * Public templates a file rule would catch.
	 *
	 * @var string[]
	 */
	private array $file_exceptions;

	/**
	 * Content signature regexes.
	 *
	 * @var string[]
	 */
	private array $content_signatures;

	/**
	 * The owner's names, matched like file rules but never excepted.
	 *
	 * @var string[]
	 */
	private array $owner_files = array();

	/**
	 * Pairs of a canonical folder and a path WordPress names it by, where they differ.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	private array $aliases = array();

	/**
	 * File names that matter only in WordPress's own folder.
	 *
	 * @var string[]
	 */
	private array $site_root_files = array();

	/**
	 * Directory names that matter only in WordPress's own folder.
	 *
	 * @var string[]
	 */
	private array $site_root_directories = array();

	/**
	 * WordPress's own folder by every name it has; null while unknown, when
	 * the site-root rules apply in every folder.
	 *
	 * @var string[]|null
	 */
	private ?array $site_root = null;

	/**
	 * Hold the rules.
	 *
	 * @param string[] $directories        Directory rules.
	 * @param string[] $paths              Path rules.
	 * @param string[] $files              File rules.
	 * @param string[] $file_exceptions    Public templates a file rule would catch.
	 * @param string[] $content_signatures Content signature regexes.
	 */
	public function __construct( array $directories, array $paths, array $files, array $file_exceptions = array(), array $content_signatures = array() ) {
		$this->directories        = $directories;
		$this->paths              = $paths;
		$this->files              = $files;
		$this->file_exceptions    = $file_exceptions;
		$this->content_signatures = $content_signatures;
	}

	/**
	 * The default rules.
	 *
	 * @return self
	 */
	public static function defaults(): self {
		$defaults                        = new self( self::DEFAULT_DIRECTORIES, self::DEFAULT_PATHS, self::DEFAULT_FILES, self::DEFAULT_FILE_EXCEPTIONS, self::DEFAULT_CONTENT_SIGNATURES );
		$defaults->site_root_files       = self::DEFAULT_SITE_ROOT_FILES;
		$defaults->site_root_directories = self::DEFAULT_SITE_ROOT_DIRECTORIES;

		return $defaults;
	}

	/**
	 * These rules, with the site-root rules placed in WordPress's own folder.
	 *
	 * @param string[] $names WordPress's own folder by every name it has, as absolute paths. None leaves the site-root rules applying everywhere.
	 * @return self
	 */
	public function with_site_root( array $names ): self {
		$copy  = clone $this;
		$names = array_values( array_filter( array_map( static fn( $name ): string => rtrim( (string) $name, '/' ), $names ) ) );
		if ( array() !== $names ) {
			$copy->site_root = $names;
		}

		return $copy;
	}

	/**
	 * No rules at all.
	 *
	 * @return self
	 */
	public static function none(): self {
		return new self( array(), array(), array() );
	}

	/**
	 * These rules plus the site owner's own files and folders, which are
	 * added to the rules and never replace them.
	 *
	 * An entry without a slash, such as "invoices" or "*.sql", is a name:
	 * it matches any file or folder with that name, and copies of such a
	 * file, such as "secrets.json.bak". An entry with a slash, such as
	 * "wp-content/uploads/invoices", is a path: it matches that file or
	 * folder and everything in it. Both may use `*`, and case is ignored.
	 *
	 * @param string[] $entries The owner's entries; malformed ones are skipped.
	 * @return self
	 */
	public function with_owner_entries( array $entries ): self {
		$copy = clone $this;
		foreach ( $entries as $entry ) {
			$entry = self::owner_entry( (string) $entry );
			if ( null === $entry ) {
				continue;
			}
			if ( false === strpos( $entry, '/' ) ) {
				$copy->directories[] = $entry;
				$copy->owner_files[] = $entry;
				continue;
			}
			$copy->paths[] = '*/' . $entry;
			$copy->paths[] = '*/' . $entry . '/*';
		}

		return $copy;
	}

	/**
	 * One owner entry as it is stored: trimmed, with forward slashes and no
	 * leading "./", "/" or trailing "/". Null when it is empty, too long,
	 * holds a control character or a ".." step, or matches everything.
	 *
	 * @param string $entry What the owner typed.
	 * @return string|null
	 */
	public static function owner_entry( string $entry ): ?string {
		$entry = trim( str_replace( '\\', '/', $entry ) );
		while ( 0 === strpos( $entry, './' ) ) {
			$entry = substr( $entry, 2 );
		}
		$entry = trim( $entry, '/' );
		if ( '' === $entry || strlen( $entry ) > self::MAX_OWNER_ENTRY_BYTES || 1 === preg_match( '/[\x00-\x1F\x7F]/', $entry ) ) {
			return null;
		}
		$segments = explode( '/', $entry );
		if ( in_array( '..', $segments, true ) || in_array( '', $segments, true ) || '' === trim( $entry, '*/.' ) ) {
			return null;
		}

		return $entry;
	}

	/**
	 * True when the path lies under a denied directory or path rule, which
	 * makes every file under it content-denied. Metadata stays visible.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	public function denies_subtree( string $path ): bool {
		if ( array() !== $this->directories ) {
			foreach ( explode( '/', trim( $path, '/' ) ) as $segment ) {
				if ( '' !== $segment && Glob_Pattern::any_matches( $this->directories, $segment, true ) ) {
					return true;
				}
			}
		}

		return ( array() !== $this->paths && Glob_Pattern::any_matches( $this->paths, $path, true ) ) || $this->denies_site_root_subtree( $path );
	}

	/**
	 * True when the path lies under a site-root directory rule: in WordPress's
	 * own folder, or in any folder while that folder is unknown.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	private function denies_site_root_subtree( string $path ): bool {
		if ( array() === $this->site_root_directories ) {
			return false;
		}
		if ( null === $this->site_root ) {
			foreach ( explode( '/', trim( $path, '/' ) ) as $segment ) {
				if ( '' !== $segment && Glob_Pattern::any_matches( $this->site_root_directories, $segment, true ) ) {
					return true;
				}
			}

			return false;
		}
		foreach ( $this->site_root as $root ) {
			if ( 0 !== strpos( $path, $root . '/' ) ) {
				continue;
			}
			$first = strtok( substr( $path, strlen( $root ) + 1 ), '/' );
			if ( false !== $first && Glob_Pattern::any_matches( $this->site_root_directories, $first, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * True when the file's content must not be read, judged by name.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	public function denies_content( string $path ): bool {
		foreach ( $this->aliases as $alias ) {
			list( $real, $logical ) = $alias;
			if ( 0 === strpos( $path . '/', $real . '/' ) && $this->denies_name( $logical . substr( $path, strlen( $real ) ) ) ) {
				return true;
			}
		}

		return $this->denies_name( $path );
	}

	/**
	 * These rules, also applied to each path as WordPress names it where a
	 * folder WordPress names is a link, such as an uploads folder that
	 * points to a shared folder: rules such as "uploads/private" then match
	 * the shared folder's files too.
	 *
	 * @param array<string, string|string[]> $aliases Canonical folder => each path WordPress names it by.
	 * @return self
	 */
	public function with_aliases( array $aliases ): self {
		$copy = clone $this;
		foreach ( $aliases as $real => $names ) {
			$real = rtrim( (string) $real, '/' );
			foreach ( (array) $names as $logical ) {
				$logical = rtrim( (string) $logical, '/' );
				if ( '' !== $real && '' !== $logical && $real !== $logical ) {
					$copy->aliases[] = array( $real, $logical );
				}
			}
		}

		return $copy;
	}

	/**
	 * These rules plus files and folders the owner excluded, written as
	 * absolute globs over where they really are, so a link to them, or a
	 * working directory inside them, cannot reach their content. Literal
	 * parts of each glob are escaped by the caller.
	 *
	 * @param string[] $globs Absolute globs; each also covers everything under it.
	 * @return self
	 */
	public function with_canonical_globs( array $globs ): self {
		$copy = clone $this;
		foreach ( $globs as $glob ) {
			$glob = rtrim( (string) $glob, '/' );
			if ( 0 === strpos( $glob, '/' ) && '' !== trim( $glob, '/*' ) ) {
				$copy->paths[] = $glob;
				$copy->paths[] = $glob . '/*';
			}
		}

		return $copy;
	}

	/**
	 * True when the file's content must not be read, judged by this path's name.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	private function denies_name( string $path ): bool {
		if ( $this->denies_subtree( $path ) ) {
			return true;
		}
		$files = $this->in_site_root( $path ) ? array_merge( $this->files, $this->site_root_files ) : $this->files;
		if ( array() === $files && array() === $this->owner_files ) {
			return false;
		}

		// Every step a copy's name takes back to the original: "payroll.txt.bak"
		// is checked as itself, "payroll.txt" and "payroll".
		$names = self::name_steps( basename( $path ) );
		if ( null === $names ) {
			return true; // A name that cannot be judged in full is refused, never allowed.
		}
		foreach ( $names as $candidate ) {
			// The owner's entries have no exceptions.
			if ( Glob_Pattern::any_matches( $this->owner_files, $candidate, true ) ) {
				return true;
			}
		}
		foreach ( $names as $candidate ) {
			if ( in_array( strtolower( $candidate ), array_map( 'strtolower', $this->file_exceptions ), true ) ) {
				return false;
			}
		}
		foreach ( $names as $candidate ) {
			if ( Glob_Pattern::any_matches( $files, $candidate, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * True when the path is directly in WordPress's own folder, or that folder is unknown.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	private function in_site_root( string $path ): bool {
		return null === $this->site_root || in_array( dirname( $path ), $this->site_root, true );
	}

	/**
	 * True when text a command printed holds a credential by content, whatever
	 * its file is called. A signature the regex engine cannot finish judging
	 * refuses the text, never allows it.
	 *
	 * @param string $sample The text.
	 * @return bool
	 */
	public function denies_sample( string $sample ): bool {
		foreach ( $this->content_signatures as $signature ) {
			if ( 0 !== preg_match( $signature, $sample ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Strip the suffixes, prefixes, and swap-file decorations that copies
	 * acquire, so the rule for the original applies to the copy.
	 *
	 * @param string $name A base name.
	 * @return string
	 */
	public static function normalize_name( string $name ): string {
		if ( strlen( $name ) > 2 && 0 === strpos( $name, '#' ) && '#' === substr( $name, -1 ) ) {
			$name = substr( $name, 1, -1 );
		}

		for ( $round = 0; $round < 8; ++$round ) {
			if ( preg_match( self::VIM_SWAP, $name, $parts ) ) {
				$name = $parts[1];
			} elseif ( preg_match( self::BACKUP_SUFFIX, $name, $parts ) ) {
				$name = $parts[1];
			} elseif ( preg_match( self::BACKUP_INFIX, $name, $parts ) ) {
				$name = $parts[1] . $parts[2];
			} elseif ( preg_match( self::BACKUP_PREFIX, $name, $parts ) ) {
				$name = $parts[1];
			} else {
				break;
			}
		}

		return $name;
	}

	/**
	 * The name and every name a copy's decorations strip to, in any order:
	 * "copy-of-payroll.txt" gives "payroll.txt" as well as "copy-of-payroll"
	 * and "payroll", which normalize_name() reports.
	 *
	 * @param string $name A base name.
	 * @return string[]|null Null when the name strips to more names than are checked, so it cannot be judged.
	 */
	private static function name_steps( string $name ): ?array {
		$steps = array( $name );
		$next  = 0;
		while ( isset( $steps[ $next ] ) ) {
			if ( isset( $steps[ self::MAX_NAME_STEPS ] ) ) {
				return null;
			}
			$current = $steps[ $next ];
			$found   = array();
			++$next;
			if ( strlen( $current ) > 2 && 0 === strpos( $current, '#' ) && '#' === substr( $current, -1 ) ) {
				$found[] = substr( $current, 1, -1 );
			}
			if ( preg_match( self::VIM_SWAP, $current, $parts ) ) {
				$found[] = $parts[1];
			}
			if ( preg_match( self::BACKUP_SUFFIX, $current, $parts ) ) {
				$found[] = $parts[1];
			}
			if ( preg_match( self::BACKUP_INFIX, $current, $parts ) ) {
				$found[] = $parts[1] . $parts[2];
			}
			if ( preg_match( self::BACKUP_PREFIX, $current, $parts ) ) {
				$found[] = $parts[1];
			}
			foreach ( $found as $stripped ) {
				if ( '' !== $stripped && ! in_array( $stripped, $steps, true ) ) {
					$steps[] = $stripped;
				}
			}
		}

		return $steps;
	}
}
