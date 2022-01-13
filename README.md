# Description

This module allows you to use local phtml template file as fallback instead of cms content 

# Install

`composer require ghostunicorns/module-cms-fallback`

# How to use
The specified fallback template will be rendered if block is missing.

In your cms block layout declaration, just add the extra argument `fallback_template` specifying the fallback template:

```
<block class="Magento\Cms\Block\Block" name="block-name">
    <arguments>
        <argument name="block_id"
                  xsi:type="string">block-identifier</argument>
        <argument name="fallback_template"
                  xsi:type="string">Vendor_Module::template.phtml</argument>
    </arguments>
</block>
```

# How to import
You can transfer all the new file-based block template to Magento CMS Block by running:

```
bin/magento cms-fallback:import --storeview default
```

You can also add `--force` to override the existing blocks.

# Contribution

Yes, of course you can contribute sending a pull request to propose improvements and fixes.

