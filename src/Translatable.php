<?php

namespace YesWeDev\Nova\Translatable;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Field;

class Translatable extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'translatable';

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|null  $attribute
     * @param  mixed|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        $locales = array_map(function ($value) {
            return __($value);
        }, config('translatable.locales'));

        $this->withMeta([
            'locales' => $locales,
            'indexLocale' => app()->getLocale()
        ]);
    }

    /**
     * Resolve the given attribute from the given resource.
     *
     * @param  mixed  $resource
     * @param  string  $attribute
     * @return mixed
     */
    protected function resolveAttribute($resource, $attribute)
    {
        $results = [];

        if (is_object($resource)) {
            if ( class_exists('\Spatie\Translatable\TranslatableServiceProvider') && method_exists($resource, 'getTranslations') ) {
                $results = $resource->getTranslations($attribute);
            } elseif ( class_exists('\Astrotomic\Translatable\TranslatableServiceProvider') && method_exists($resource, 'translations') ) {
                $results =  $resource->translations->pluck($attribute, config('translatable.locale_key'));
            }
        }
        
        $results = data_get($resource, $attribute);
        
        return $results;
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  string  $requestAttribute
     * @param  object  $model
     * @param  string  $attribute
     * @return void
     */
    protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if ( class_exists('\Astrotomic\Translatable\TranslatableServiceProvider') && method_exists($model, 'translateOrNew') ) {
            if ( is_array($request[$requestAttribute]) ) {
                foreach ( $request[$requestAttribute] as $lang => $value ) {
                    $model->translateOrNew($lang)->{$attribute} = $value;
                }
            }
        } else {
            parent::fillAttributeFromRequest($request, $requestAttribute, $model, $attribute);
        }
    }

    /**
     * Set the locales to display / edit.
     *
     * @param  array  $locales
     * @return $this
     */
    public function locales(array $locales)
    {
        return $this->withMeta(['locales' => $locales]);
    }

    /**
     * Set the locale to display on index.
     *
     * @param  string $locale
     * @return $this
     */
    public function indexLocale($locale)
    {
        return $this->withMeta(['indexLocale' => $locale]);
    }

    /**
     * Set the input field to a single line text field.
     */
    public function singleLine()
    {
        return $this->withMeta(['singleLine' => true]);
    }

    /**
     * Use Trix Editor.
     */
    public function trix()
    {
        return $this->withMeta(['trix' => true]);
    }

    /**
     * Display the field as raw HTML.
     */
    public function asHtml()
    {
        return $this->withMeta(['asHtml' => true]);
    }

    /**
     * Truncate on Detail Page.
     */
    public function truncate()
    {
        return $this->withMeta(['truncate' => true]);
    }

    /**
     * Override the default isRequired behaviour to support custom required rule
     *
     * @param NovaRequest $request
     * @return mixed
     */
    public function isRequired(NovaRequest $request)
    {
        $requiredRuleName = config('nova-translatable.required_rule_name', 'required');
        return with($this->requiredCallback, function ($callback) use ($request, $requiredRuleName) {
            if ($callback === true || (is_callable($callback) && call_user_func($callback, $request))) {
                return true;
            }

            if (!empty($this->attribute) && is_null($callback)) {
                if ($request->isResourceIndexRequest() || $request->isActionRequest()) {
                    return in_array($requiredRuleName, $this->getCreationRules($request)[$this->attribute]);
                }

                if ($request->isCreateOrAttachRequest()) {
                    return in_array($requiredRuleName, $this->getCreationRules($request)[$this->attribute]);
                }

                if ($request->isUpdateOrUpdateAttachedRequest()) {
                    return in_array($requiredRuleName, $this->getUpdateRules($request)[$this->attribute]);
                }
            }

            return false;
        });
    }
}
