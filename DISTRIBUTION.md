# Publishing BedrockProtocol for RyzeMP

Composer package: `ryzemcbe/bedrock-protocol` (library). Source: https://github.com/RyzeCompany/BedrockProtocol.

Multiversion Minecraft: Bedrock protocol support used by RyzeMP. The PHP namespace remains `pocketmine\network\mcpe\protocol\` for backwards-compatible plugin integration.

## Public package release checklist

1. Verify the exact revision against the matching RyzeMP core and the other Bedrock dependency.
2. Make this repository public after reviewing its contents and license; a private repository cannot be submitted to public Packagist.org as an installable source package.
3. Create a tested SemVer prerelease tag on `main`, for example `v1.0.0-beta.3` **only if** that version has actually been tested and designated.
4. Submit this repository URL on https://packagist.org/packages/submit and enable its GitHub update hook.
5. Confirm the tagged version resolves and can be downloaded via Composer without special repository configuration.

Once the corresponding release is available on Packagist, developers can install it with:

```bash
composer require ryzemcbe/bedrock-protocol:<released-version>
```

Do not publish or recommend `dev-main` as a stable API contract. Tags, package registration, and an end-to-end RyzeMP CI build are required for release readiness.
