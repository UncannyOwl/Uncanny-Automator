<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Publishing\PagePublicationFailed;
use UncannyPageBuilder\Application\Publishing\PagePublicationOutcome;
use UncannyPageBuilder\Application\SourcePackage\PageSourceExportException;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\EditableUpdateException;
use UncannyPageBuilder\Domain\Exception\HistorySnapshotConflictException;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Kernel\Container;

final class ControlDispatcher
{
    public function __construct(
        private readonly ControlRegistry $registry,
        private readonly Container $container,
        private readonly PermissionChecker $permissions,
        private readonly SectionRepositoryInterface $sectionRepository,
    ) {}

    public function invoke(ControlInvokeRequest $request): ControlInvokeResult|\WP_Error
    {
        $definition = $this->registry->get($request->controlId());
        if ($definition === null) {
            return ApiResponse::error(ErrorMessage::ControlUnknown);
        }

        if (!$definition->supportsContext($request->context())) {
            return ApiResponse::error(ErrorMessage::ControlUnsupportedContext);
        }

        $permissionError = $this->authorize($definition, $request->context());
        if ($permissionError !== null) {
            return $permissionError;
        }

        $handler = $definition->handler();
        if ($handler === null) {
            return ApiResponse::error(ErrorMessage::ControlNotInvokable);
        }

        try {
            $handlerInstance = $this->resolveHandler($handler, $definition->id());
            return $handlerInstance($request);
        } catch (PageSourceExportException $e) {
            return new \WP_Error(
                'PageSourceExportFailed',
                $e->getMessage(),
                [
                    'status' => $e->httpStatus(),
                    'control_id' => $definition->id(),
                ],
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(ErrorMessage::ControlInvalidRequest, [
                'control_id' => $definition->id(),
                'detail'     => $e->getMessage(),
            ]);
        } catch (EditableUpdateException $e) {
            return ApiResponse::error($this->editableUpdateError($e), [
                'control_id'   => $definition->id(),
                'editable_key' => $e->editableKey(),
                'detail'       => $e->getMessage(),
            ]);
        } catch (SectionValidationException $e) {
            return ApiResponse::validationError($e);
        } catch (PageNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException $e) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (HistorySnapshotConflictException $e) {
            return ApiResponse::error(ErrorMessage::StaleHistorySnapshot, [
                'control_id' => $definition->id(),
            ]);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, [
                'control_id' => $definition->id(),
                'scope' => $e->scope(),
            ]);
        } catch (PagePublicationFailed $e) {
            return ApiResponse::error($this->publicationError($e->outcome()), [
                'control_id' => $definition->id(),
                'outcome' => $e->outcome()->value,
                'detail' => $e->getMessage(),
                'details' => $e->details(),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, [
                'control_id' => $definition->id(),
            ]);
        }
    }

    private function authorize(ControlDefinition $definition, ControlContext $context): ?\WP_Error
    {
        $capability = $definition->capability();
        if (is_string($capability) && $capability !== '') {
            $canUseCapability = $capability === 'publish_post'
                ? $this->permissions->canPublishPost($context->pageId())
                : $this->permissions->canCapability($capability);
            if (!$canUseCapability) {
                return ApiResponse::error(ErrorMessage::ControlInvokeForbidden);
            }
        }

        if ($context->pageId() > 0) {
            if (!$this->sectionRepository->isOwnedPage($context->pageId())) {
                return ApiResponse::error(ErrorMessage::PageNotOwned);
            }

            return $this->permissions->canEditPage($context->pageId())
                ? null
                : ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if ($context->globalPartId() > 0) {
            return $this->permissions->canEditPost($context->globalPartId())
                ? null
                : ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
        }

        return ApiResponse::error(ErrorMessage::MissingPageId);
    }

    /** @param class-string<ControlHandlerInterface>|callable $handler */
    private function resolveHandler(mixed $handler, string $controlId): ControlHandlerInterface
    {
        if ($handler instanceof ControlHandlerInterface) {
            return $handler;
        }

        if (is_callable($handler) && !is_string($handler)) {
            $resolved = $handler();
            if (!$resolved instanceof ControlHandlerInterface) {
                throw new \RuntimeException(sprintf('Factory for "%s" must return ControlHandlerInterface.', $controlId));
            }

            return $resolved;
        }

        if (!is_string($handler)) {
            throw new \RuntimeException(sprintf('Handler for "%s" is invalid.', $controlId));
        }

        $resolved = $this->container->has($handler)
            ? $this->container->typed($handler)
            : new $handler();

        if (!$resolved instanceof ControlHandlerInterface) {
            throw new \RuntimeException(sprintf('Handler for "%s" must implement ControlHandlerInterface.', $controlId));
        }

        return $resolved;
    }

    private function editableUpdateError(EditableUpdateException $e): ErrorMessage
    {
        return match ($e->reason()) {
            'key_not_found' => ErrorMessage::EditableKeyNotFound,
            'type_mismatch' => ErrorMessage::EditableTypeMismatch,
            'duplicate_key' => ErrorMessage::EditableDuplicateKey,
            'nested_markup' => ErrorMessage::EditableHasNestedMarkup,
            default => ErrorMessage::ControlInvalidRequest,
        };
    }

    private function publicationError(PagePublicationOutcome $outcome): ErrorMessage
    {
        return match ($outcome) {
            PagePublicationOutcome::NotAuthorized => ErrorMessage::ControlInvokeForbidden,
            PagePublicationOutcome::StaleSource => ErrorMessage::StaleSourceGeneration,
            PagePublicationOutcome::StaticSafetyFailed => ErrorMessage::PublicationStaticSafetyFailed,
            PagePublicationOutcome::NothingToPublish => ErrorMessage::PublicationNothingToPublish,
            PagePublicationOutcome::SlugConflict => ErrorMessage::PublicationSlugConflict,
            PagePublicationOutcome::ArtifactPersistFailed => ErrorMessage::PublicationArtifactPersistFailed,
            PagePublicationOutcome::PublicStateCommitFailed => ErrorMessage::PublicationCommitFailed,
            PagePublicationOutcome::Published => ErrorMessage::ControlInvokeFailed,
        };
    }
}
